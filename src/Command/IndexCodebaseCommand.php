<?php

namespace App\Command;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\IndexerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Indexes a small, curated set of documentation files into the SQLite vector
 * store that powers the Code Chat RAG pipeline.
 *
 * Only the three files listed in INDEXED_FILES are processed: README.md,
 * doc/project.md, and doc/db.md. Restricting the scope to high-signal
 * documentation avoids UTF-8 encoding problems found in source/template files
 * and keeps the number of Ollama embedding requests small.
 *
 * Run once after setup, and again with --truncate whenever the docs change.
 *
 * Usage:
 *   php bin/console app:index-codebase
 *   php bin/console app:index-codebase --truncate   # re-index from scratch
 *   php bin/console app:index-codebase --dry-run    # preview files without indexing
 */
#[AsCommand(
    name: 'app:index-codebase',
    description: 'Indexes project source files into the code vector store for RAG-based Code Chat.',
)]
class IndexCodebaseCommand extends Command
{
    /**
     * Maximum characters per chunk. Keeps each chunk well within the
     * nomic-embed-text token limit (~8192 tokens).
     */
    private const CHUNK_SIZE = 4000;

    /**
     * Character overlap between consecutive chunks to avoid losing context
     * at chunk boundaries.
     */
    private const CHUNK_OVERLAP = 200;

    /**
     * Only these specific files are indexed. Keeping the scope to the three
     * most information-dense documentation files avoids UTF-8 encoding issues
     * found in source/template files and reduces Ollama round-trips to a
     * manageable number.
     */
    private const INDEXED_FILES = [
        'README.md',
        'doc/project.md',
        'doc/db.md',
    ];

    /**
     * @param IndexerInterface      $indexer    Document indexer wired to the `code` vectorizer and SQLite store.
     * @param ManagedStoreInterface $store      Managed SQLite store used to clear all chunks when --truncate is passed.
     * @param string                $projectDir Kernel project directory prepended to each path in INDEXED_FILES.
     */
    public function __construct(
        #[Autowire('@ai.indexer.code')] private readonly IndexerInterface $indexer,
        #[Autowire('@ai.store.sqlite.code')] private readonly ManagedStoreInterface $store,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    /**
     * Registers the --truncate and --dry-run options.
     */
    protected function configure(): void
    {
        $this
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Clear the store before indexing')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List files without indexing');
    }

    /**
     * Scans the project files, builds TextDocument chunks, and indexes them.
     *
     * Each chunk stores the raw text in Metadata::KEY_TEXT so that CodeChatService
     * can read it back via VectorDocument::getMetadata()->getText() at query time.
     * The source file path is stored in Metadata::KEY_SOURCE.
     *
     * @param InputInterface  $input  Console input carrying the --truncate and --dry-run flags.
     * @param OutputInterface $output Console output used via SymfonyStyle for progress display.
     *
     * @return int Command::SUCCESS on success, Command::FAILURE on unrecoverable error.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Codebase Indexer');

        if ($input->getOption('truncate')) {
            $this->store->drop();
            $this->store->setup();
            $io->comment('Store cleared.');
        }

        $files = array_filter(
            array_map(fn ($f) => $this->projectDir.'/'.$f, self::INDEXED_FILES),
            'is_file'
        );

        $io->comment(sprintf('Found %d files to process.', count($files)));

        if ($input->getOption('dry-run')) {
            foreach ($files as $path) {
                $io->writeln('  '.ltrim(str_replace($this->projectDir, '', $path), '/'));
            }

            return Command::SUCCESS;
        }

        $documents = [];
        $fileCount = 0;

        foreach ($files as $path) {
            $content = $this->sanitizeUtf8(file_get_contents($path) ?: '');
            if (trim($content) === '') {
                continue;
            }

            ++$fileCount;
            $relativePath = ltrim(str_replace($this->projectDir, '', $path), '/');

            foreach ($this->splitIntoChunks($content) as $i => $chunk) {
                $chunk = $this->sanitizeUtf8($chunk);
                if (trim($chunk) === '') {
                    continue;
                }

                $metadata = new Metadata();
                $metadata->offsetSet(Metadata::KEY_SOURCE, $relativePath);
                $metadata->setText($chunk);

                $documents[] = new TextDocument(
                    id: sprintf('%s::%d', $relativePath, $i),
                    content: $chunk,
                    metadata: $metadata,
                );
            }
        }

        if ([] === $documents) {
            $io->warning('No documents to index.');

            return Command::SUCCESS;
        }

        $io->progressStart(count($documents));
        // chunk_size=1 avoids AsyncResponse conflicts with Symfony's traceable HTTP client
        $this->indexer->index($documents, ['chunk_size' => 1]);
        $io->progressFinish();

        $io->success(sprintf('Indexed %d files into %d chunks.', $fileCount, count($documents)));

        return Command::SUCCESS;
    }

    /**
     * Strips invalid UTF-8 byte sequences from a string so it can be safely
     * JSON-encoded and sent to the Ollama embedding API.
     *
     * Uses mb_scrub() (PHP 8.1+) which replaces ill-formed byte sequences with
     * the Unicode replacement character and is the canonical way to sanitize
     * UTF-8 before JSON serialization.
     *
     * @param string $content Raw file content, potentially containing non-UTF-8 bytes.
     *
     * @return string Content with invalid UTF-8 sequences removed.
     */
    private function sanitizeUtf8(string $content): string
    {
        return mb_scrub($content, 'UTF-8');
    }

    /**
     * Splits a file's content into overlapping chunks of at most CHUNK_SIZE characters.
     *
     * Attempts to break at newline boundaries to avoid splitting mid-line. The last
     * chunk may be shorter than CHUNK_SIZE. A CHUNK_OVERLAP overlap is kept between
     * consecutive chunks to preserve cross-boundary context.
     *
     * @param string $content Raw file content to split.
     *
     * @return string[] Array of text chunks, each at most CHUNK_SIZE characters long.
     */
    private function splitIntoChunks(string $content): array
    {
        if (strlen($content) <= self::CHUNK_SIZE) {
            return [$content];
        }

        $chunks = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $end = min($offset + self::CHUNK_SIZE, $length);

            if ($end < $length) {
                $newline = strrpos(substr($content, $offset, self::CHUNK_SIZE), "\n");
                if ($newline !== false && $newline > self::CHUNK_SIZE / 2) {
                    $end = $offset + $newline + 1;
                }
            }

            $chunks[] = substr($content, $offset, $end - $offset);
            $offset = max($offset + 1, $end - self::CHUNK_OVERLAP);
        }

        return $chunks;
    }
}
