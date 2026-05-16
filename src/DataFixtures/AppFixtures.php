<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonProgress;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * Populates the database with randomised seed data for development and testing.
 *
 * Volumes (menu table excluded as requested):
 *  - users          : 100 (20 instructors + 80 students)
 *  - categories     : 100
 *  - courses        : 100
 *  - lessons        : 500 (5 per course)
 *  - enrollments    : 100 (unique student–course pairs)
 *  - lesson_progress: 200 (2 per enrollment, from the enrollment's own course)
 *  - reviews        : 100 (1 per enrollment)
 *
 * All data is generated procedurally using FakerPHP, so no record is hardcoded.
 */
class AppFixtures extends Fixture
{
    private const INSTRUCTORS_COUNT         = 20;
    private const STUDENTS_COUNT            = 80;
    private const CATEGORIES_COUNT          = 100;
    private const COURSES_COUNT             = 100;
    private const LESSONS_PER_COURSE        = 5;
    private const ENROLLMENTS_COUNT         = 100;
    private const PROGRESS_PER_ENROLLMENT   = 2;

    private const COURSE_LEVELS = ['beginner', 'intermediate', 'advanced'];

    private const LESSON_TITLE_PREFIXES = [
        'Introduction to', 'Fundamentals of', 'Advanced', 'Practical', 'Deep dive into',
        'Overview of', 'Working with', 'Mastering', 'Exercises on', 'Review of',
    ];

    private const REVIEW_COMMENTS = [
        'Corso eccellente, ho imparato moltissimo!',
        'Ottima qualità dei contenuti, lo consiglio vivamente.',
        'Molto chiaro e ben strutturato. Ottimo lavoro.',
        'Interessante, ma alcuni argomenti potrebbero essere più approfonditi.',
        'Buon corso nel complesso, con esempi pratici molto utili.',
        'Ottimo rapporto qualità-prezzo. Lo rifarei.',
        'Docente molto preparato e comunicativo.',
        'Contenuti aggiornati e immediatamente applicabili.',
        'Corso completo e ben organizzato. Nulla da aggiungere.',
        'Potrebbe avere più esercizi pratici, ma rimane di alta qualità.',
        'Finalmente un corso che spiega le cose senza dare nulla per scontato.',
        'Ho completato il corso in un weekend. Struttura perfetta.',
        'Qualche video un po\' lungo, ma il contenuto è solido.',
        'Superato le aspettative. Lo consiglio a tutti.',
        'Mi aspettavo di più dal livello avanzato, ma rimane utile.',
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('it_IT');

        $categories  = $this->createCategories($manager, $faker);
        $instructors = $this->createUsers($manager, $faker, 'instructor', self::INSTRUCTORS_COUNT);
        $students    = $this->createUsers($manager, $faker, 'student', self::STUDENTS_COUNT);
        $courses     = $this->createCourses($manager, $faker, $categories, $instructors);

        /** @var array<int, list<Lesson>> $lessonsByCourseIndex */
        $lessonsByCourseIndex = $this->createLessons($manager, $faker, $courses);

        // Flush everything created so far so that Enrollment / LessonProgress / Review
        // can reference already-managed entities without needing IDs yet.
        $manager->flush();

        $enrollments = $this->createEnrollments($manager, $students, $courses);
        $this->createLessonProgress($manager, $enrollments, $lessonsByCourseIndex);
        $this->createReviews($manager, $faker, $enrollments);

        $manager->flush();
    }

    // -------------------------------------------------------------------------
    // Private builders
    // -------------------------------------------------------------------------

    /**
     * Creates 100 unique categories using randomised Italian/English topic words.
     *
     * @param ObjectManager $manager
     * @param Generator     $faker
     * @return list<Category>
     */
    private function createCategories(ObjectManager $manager, Generator $faker): array
    {
        $categories  = [];
        $usedNames   = [];

        for ($i = 0; $i < self::CATEGORIES_COUNT; $i++) {
            // Guarantee uniqueness by appending a numeric suffix when needed.
            $base = ucwords($faker->words(rand(1, 3), true));
            $name = isset($usedNames[$base]) ? $base . ' ' . ($i + 1) : $base;
            $usedNames[$name] = true;

            $category = new Category();
            $category->setName($name);
            $category->setDescription($faker->optional(0.7)->paragraph(rand(1, 3)));

            $manager->persist($category);
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Creates a batch of users all sharing the same role.
     *
     * @param ObjectManager $manager
     * @param Generator     $faker
     * @param 'student'|'instructor' $role
     * @param int           $count
     * @return list<User>
     */
    private function createUsers(ObjectManager $manager, Generator $faker, string $role, int $count): array
    {
        $users      = [];
        $usedEmails = [];

        // Shared hash for all fixture users (never store plain text outside tests).
        $passwordHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 4]);

        for ($i = 0; $i < $count; $i++) {
            $user = new User();
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());

            // Ensure email uniqueness across all batches by using a numeric suffix.
            do {
                $email = $faker->unique()->safeEmail();
            } while (isset($usedEmails[$email]));
            $usedEmails[$email] = true;

            $user->setEmail($email);
            $user->setPasswordHash($passwordHash);
            $user->setRole($role);

            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Creates 100 courses, each linked to a random category and a random instructor.
     *
     * @param ObjectManager  $manager
     * @param Generator      $faker
     * @param list<Category> $categories
     * @param list<User>     $instructors
     * @return list<Course>
     */
    private function createCourses(
        ObjectManager $manager,
        Generator $faker,
        array $categories,
        array $instructors
    ): array {
        $courses    = [];
        $usedSlugs  = [];

        for ($i = 0; $i < self::COURSES_COUNT; $i++) {
            $course = new Course();
            $course->setCategory($categories[array_rand($categories)]);
            $course->setInstructor($instructors[array_rand($instructors)]);

            $title = rtrim($faker->sentence(rand(3, 6)), '.');
            $course->setTitle($title);

            // Guarantee slug uniqueness with the loop index as disambiguator.
            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
            $slug     = isset($usedSlugs[$baseSlug]) ? $baseSlug . '-' . $i : $baseSlug;
            $usedSlugs[$slug] = true;
            $course->setSlug($slug);

            $course->setDescription($faker->optional(0.8)->paragraphs(rand(1, 3), true));
            $course->setPrice((string) number_format(rand(0, 19900) / 100, 2));
            $course->setLevel(self::COURSE_LEVELS[array_rand(self::COURSE_LEVELS)]);

            // ~70 % of courses are published.
            if (rand(1, 10) <= 7) {
                $course->setPublished(true);
            }

            $manager->persist($course);
            $courses[] = $course;
        }

        return $courses;
    }

    /**
     * Creates LESSONS_PER_COURSE lessons for each course, with sequential positionOrder.
     *
     * @param ObjectManager $manager
     * @param Generator     $faker
     * @param list<Course>  $courses
     * @return array<int, list<Lesson>>  Lessons indexed by course position in the $courses array.
     */
    private function createLessons(ObjectManager $manager, Generator $faker, array $courses): array
    {
        $lessonsByCourseIndex = [];

        foreach ($courses as $courseIndex => $course) {
            $lessonsByCourseIndex[$courseIndex] = [];

            for ($position = 1; $position <= self::LESSONS_PER_COURSE; $position++) {
                $prefix = self::LESSON_TITLE_PREFIXES[($position - 1) % count(self::LESSON_TITLE_PREFIXES)];
                $topic  = $faker->words(rand(1, 3), true);

                $lesson = new Lesson();
                $lesson->setCourse($course);
                $lesson->setTitle($prefix . ' ' . ucfirst($topic));
                $lesson->setContent($faker->optional(0.75)->paragraphs(rand(2, 5), true));
                $lesson->setDurationMinutes(rand(5, 60));
                $lesson->setPositionOrder($position);

                $manager->persist($lesson);
                $lessonsByCourseIndex[$courseIndex][] = $lesson;
            }
        }

        return $lessonsByCourseIndex;
    }

    /**
     * Creates ENROLLMENTS_COUNT unique (student, course) enrollment pairs.
     *
     * Returns an array of maps with keys 'entity' (Enrollment) and 'courseIndex' (int)
     * so that downstream builders can resolve the correct lesson pool without re-querying.
     *
     * @param ObjectManager $manager
     * @param list<User>    $students
     * @param list<Course>  $courses
     * @return list<array{entity: Enrollment, courseIndex: int}>
     */
    private function createEnrollments(ObjectManager $manager, array $students, array $courses): array
    {
        $enrollments  = [];
        $seenPairs    = [];
        $maxAttempts  = self::ENROLLMENTS_COUNT * 50;
        $attempts     = 0;

        while (count($enrollments) < self::ENROLLMENTS_COUNT && $attempts < $maxAttempts) {
            ++$attempts;

            $studentIndex = array_rand($students);
            $courseIndex  = array_rand($courses);
            $pairKey      = $studentIndex . ':' . $courseIndex;

            if (isset($seenPairs[$pairKey])) {
                continue;
            }
            $seenPairs[$pairKey] = true;

            $enrollment = new Enrollment();
            $enrollment->setUser($students[$studentIndex]);
            $enrollment->setCourse($courses[$courseIndex]);
            $enrollment->setProgressPercent(rand(0, 100));

            // ~25 % of enrollments are marked as completed.
            if (rand(1, 4) === 1) {
                $enrollment->setCompleted(true);
            }

            $manager->persist($enrollment);
            $enrollments[] = ['entity' => $enrollment, 'courseIndex' => $courseIndex];
        }

        return $enrollments;
    }

    /**
     * Creates PROGRESS_PER_ENROLLMENT lesson-progress records per enrollment.
     *
     * Picks PROGRESS_PER_ENROLLMENT distinct lessons from the enrollment's course
     * to satisfy the composite unique constraint (enrollment_id, lesson_id).
     *
     * @param ObjectManager                                    $manager
     * @param list<array{entity: Enrollment, courseIndex: int}> $enrollments
     * @param array<int, list<Lesson>>                         $lessonsByCourseIndex
     */
    private function createLessonProgress(
        ObjectManager $manager,
        array $enrollments,
        array $lessonsByCourseIndex
    ): void {
        foreach ($enrollments as $enrollmentData) {
            $enrollment   = $enrollmentData['entity'];
            $courseIndex  = $enrollmentData['courseIndex'];
            $courseLessons = $lessonsByCourseIndex[$courseIndex];

            $pickCount = min(self::PROGRESS_PER_ENROLLMENT, count($courseLessons));
            $picked    = (array) array_rand($courseLessons, $pickCount);

            foreach ($picked as $lessonIndex) {
                $progress = new LessonProgress();
                $progress->setEnrollment($enrollment);
                $progress->setLesson($courseLessons[$lessonIndex]);
                $manager->persist($progress);
            }
        }
    }

    /**
     * Creates exactly one review per enrollment (satisfies the unique FK on enrollment_id).
     *
     * @param ObjectManager                                    $manager
     * @param Generator                                        $faker
     * @param list<array{entity: Enrollment, courseIndex: int}> $enrollments
     */
    private function createReviews(ObjectManager $manager, Generator $faker, array $enrollments): void
    {
        foreach ($enrollments as $enrollmentData) {
            $enrollment = $enrollmentData['entity'];

            $review = new Review();
            $review->setEnrollment($enrollment);
            $review->setCourse($enrollment->getCourse());
            $review->setUser($enrollment->getUser());
            $review->setRating(rand(1, 5));

            // 70 % of reviews include a text comment.
            $review->setComment($faker->optional(0.7)->randomElement(self::REVIEW_COMMENTS));

            $manager->persist($review);
        }
    }
}
