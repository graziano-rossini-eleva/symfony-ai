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
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:index-codebase',
    description: 'Indexes project source files into the code vector store for RAG-based Code Chat.',
)]
class IndexCodebaseCommand extends Command
{
    private const CHUNK_SIZE = 4000;
    private const CHUNK_OVERLAP = 200;
    private const EXTENSIONS = ['php', 'twig', 'yaml', 'yml', 'md'];
    private const EXCLUDE_DIRS = ['vendor', 'var', 'node_modules', '.git', 'public'];

    public function __construct(
        #[Autowire('@ai.indexer.code')] private readonly IndexerInterface $indexer,
        #[Autowire('@ai.store.sqlite.code')] private readonly ManagedStoreInterface $store,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Clear the store before indexing')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List files without indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Codebase Indexer');

        if ($input->getOption('truncate')) {
            $this->store->clear();
            $io->comment('Store cleared.');
        }

        $finder = (new Finder())
            ->files()
            ->in($this->projectDir)
            ->exclude(self::EXCLUDE_DIRS)
            ->name(array_map(fn ($ext) => '*.'.$ext, self::EXTENSIONS))
            ->sortByName();

        $io->comment(sprintf('Found %d files to process.', $finder->count()));

        if ($input->getOption('dry-run')) {
            foreach ($finder as $file) {
                $io->writeln('  '.$file->getRelativePathname());
            }

            return Command::SUCCESS;
        }

        $documents = [];
        $fileCount = 0;

        foreach ($finder as $file) {
            $content = $file->getContents();
            if (trim($content) === '') {
                continue;
            }

            ++$fileCount;
            $relativePath = $file->getRelativePathname();
            $chunks = $this->splitIntoChunks($content);

            foreach ($chunks as $i => $chunk) {
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
        $this->indexer->index($documents, ['chunk_size' => 10]);
        $io->progressFinish();

        $io->success(sprintf('Indexed %d files into %d chunks.', $fileCount, count($documents)));

        return Command::SUCCESS;
    }

    /** @return string[] */
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
