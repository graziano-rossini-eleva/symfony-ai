# Project: Online Course Platform

## Overview

This database schema represents an online learning platform where:

- Users can enroll in courses
- Courses contain lessons
- Instructors create courses
- Students can leave reviews
- Categories organize courses

Database engine target: MySQL 8+

---

# Table: users

Represents platform users.

| Column Name     | Type              | Constraints                                          |
|-----------------|-------------------|------------------------------------------------------|
| id              | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                          |
| first_name      | VARCHAR(100)      | NOT NULL                                             |
| last_name       | VARCHAR(100)      | NOT NULL                                             |
| email           | VARCHAR(180)      | NOT NULL, UNIQUE                                     |
| password_hash   | VARCHAR(255)      | NOT NULL                                             |
| role            | ENUM              | NOT NULL ('student','instructor')                    |
| created_at      | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                   |
| updated_at      | DATETIME          | NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP        |
| deleted         | TINYINT(1)        | NOT NULL DEFAULT 0                                   |
| deleted_at      | DATETIME          | NULL DEFAULT NULL                                    |

Indexes:
- UNIQUE INDEX idx_users_email (email)
- INDEX idx_users_deleted_at (deleted_at)

Notes:
- Soft delete via `deleted = 0` / `deleted_at`. On delete: set `deleted = 1` and `deleted_at = NOW()` atomically. Application queries filter with `WHERE deleted = 0`.
- Role enforcement (e.g. only instructors can create courses) is handled at application level via Symfony voters/guards.

---

# Table: categories

Represents course categories.

| Column Name     | Type              | Constraints                                          |
|-----------------|-------------------|------------------------------------------------------|
| id              | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                          |
| name            | VARCHAR(120)      | NOT NULL, UNIQUE                                     |
| description     | TEXT              | NULL                                                 |
| created_at      | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                   |
| deleted         | TINYINT(1)        | NOT NULL DEFAULT 0                                   |
| deleted_at      | DATETIME          | NULL DEFAULT NULL                                    |

Indexes:
- UNIQUE INDEX idx_categories_name (name)
- INDEX idx_categories_deleted_at (deleted_at)

---

# Table: courses

Represents courses created by instructors.

| Column Name     | Type              | Constraints                                          |
|-----------------|-------------------|------------------------------------------------------|
| id              | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                          |
| category_id     | BIGINT            | NOT NULL, FOREIGN KEY                                |
| instructor_id   | BIGINT            | NOT NULL, FOREIGN KEY                                |
| title           | VARCHAR(255)      | NOT NULL                                             |
| slug            | VARCHAR(255)      | NOT NULL, UNIQUE                                     |
| description     | TEXT              | NULL                                                 |
| price           | DECIMAL(10,2)     | NOT NULL DEFAULT 0.00                                |
| level           | ENUM              | NOT NULL ('beginner','intermediate','advanced')      |
| published       | TINYINT(1)        | NOT NULL DEFAULT 0                                   |
| published_at    | DATETIME          | NULL DEFAULT NULL                                    |
| created_at      | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                   |
| updated_at      | DATETIME          | NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP        |
| deleted         | TINYINT(1)        | NOT NULL DEFAULT 0                                   |
| deleted_at      | DATETIME          | NULL DEFAULT NULL                                    |

Foreign Keys:
- category_id REFERENCES categories(id)
- instructor_id REFERENCES users(id)

Indexes:
- UNIQUE INDEX idx_courses_slug (slug)
- INDEX idx_courses_category (category_id)
- INDEX idx_courses_instructor (instructor_id)
- INDEX idx_courses_published (published)
- INDEX idx_courses_level (level)
- INDEX idx_courses_deleted_at (deleted_at)

Notes:
- Soft delete via `deleted = 0` / `deleted_at`. On delete: set `deleted = 1` and `deleted_at = NOW()` atomically. Enrollments and lessons referencing a soft-deleted course remain intact for historical integrity.
- `published_at` is set when `published` transitions from 0 to 1, enabling scheduled publishing and audit trail.
- `instructor_id` must reference a user with `role = 'instructor'`. Enforced at application level.

---

# Table: lessons

Represents lessons inside a course.

| Column Name      | Type              | Constraints                                         |
|------------------|-------------------|-----------------------------------------------------|
| id               | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                         |
| course_id        | BIGINT            | NOT NULL, FOREIGN KEY                               |
| title            | VARCHAR(255)      | NOT NULL                                            |
| content          | LONGTEXT          | NULL                                                |
| video_url        | VARCHAR(500)      | NULL                                                |
| duration_minutes | INT               | NOT NULL DEFAULT 0                                  |
| position_order   | INT               | NOT NULL                                            |
| created_at       | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                  |
| deleted          | TINYINT(1)        | NOT NULL DEFAULT 0                                  |
| deleted_at       | DATETIME          | NULL DEFAULT NULL                                   |

Foreign Keys:
- course_id REFERENCES courses(id)

Indexes:
- INDEX idx_lessons_course (course_id)
- UNIQUE INDEX idx_lessons_course_position (course_id, position_order)
- INDEX idx_lessons_deleted_at (deleted_at)

Notes:
- The composite UNIQUE on `(course_id, position_order)` prevents duplicate positions within the same course.

---

# Table: enrollments

Represents course enrollments by students.

| Column Name      | Type              | Constraints                                         |
|------------------|-------------------|-----------------------------------------------------|
| id               | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                         |
| user_id          | BIGINT            | NOT NULL, FOREIGN KEY                               |
| course_id        | BIGINT            | NOT NULL, FOREIGN KEY                               |
| enrolled_at      | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                  |
| progress_percent | INT               | NOT NULL DEFAULT 0                                  |
| completed        | TINYINT(1)        | NOT NULL DEFAULT 0                                  |
| completed_at     | DATETIME          | NULL DEFAULT NULL                                   |
| deleted          | TINYINT(1)        | NOT NULL DEFAULT 0                                  |
| deleted_at       | DATETIME          | NULL DEFAULT NULL                                   |

Foreign Keys:
- user_id REFERENCES users(id)
- course_id REFERENCES courses(id)

Indexes:
- UNIQUE INDEX idx_enrollment_unique (user_id, course_id)
- INDEX idx_enrollment_user (user_id)
- INDEX idx_enrollment_course (course_id)
- INDEX idx_enrollment_deleted_at (deleted_at)

Notes:
- `progress_percent` is a derived aggregate updated when a `lesson_progress` record is saved.
- `completed_at` is set when `completed` transitions to 1, enabling accurate completion metrics.

---

# Table: lesson_progress

Tracks which lessons a student has completed within an enrollment.

| Column Name   | Type              | Constraints                                            |
|---------------|-------------------|--------------------------------------------------------|
| id            | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                            |
| enrollment_id | BIGINT            | NOT NULL, FOREIGN KEY                                  |
| lesson_id     | BIGINT            | NOT NULL, FOREIGN KEY                                  |
| completed_at  | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                     |
| deleted       | TINYINT(1)        | NOT NULL DEFAULT 0                                     |
| deleted_at    | DATETIME          | NULL DEFAULT NULL                                      |

Foreign Keys:
- enrollment_id REFERENCES enrollments(id)
- lesson_id REFERENCES lessons(id)

Indexes:
- UNIQUE INDEX idx_lesson_progress_unique (enrollment_id, lesson_id)
- INDEX idx_lesson_progress_enrollment (enrollment_id)
- INDEX idx_lesson_progress_lesson (lesson_id)
- INDEX idx_lesson_progress_deleted_at (deleted_at)

Notes:
- One row per (enrollment, lesson) pair. Insert-only: no update needed.
- After insert, application recomputes `enrollments.progress_percent` and toggles `enrollments.completed` if all lessons are done.

---

# Table: reviews

Represents reviews left by students on courses.

| Column Name   | Type              | Constraints                                            |
|---------------|-------------------|--------------------------------------------------------|
| id            | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                            |
| enrollment_id | BIGINT            | NOT NULL, FOREIGN KEY                                  |
| course_id     | BIGINT            | NOT NULL, FOREIGN KEY                                  |
| user_id       | BIGINT            | NOT NULL, FOREIGN KEY                                  |
| rating        | TINYINT           | NOT NULL, CHECK (rating BETWEEN 1 AND 5)               |
| comment       | TEXT              | NULL                                                   |
| created_at    | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                     |
| deleted       | TINYINT(1)        | NOT NULL DEFAULT 0                                     |
| deleted_at    | DATETIME          | NULL DEFAULT NULL                                      |

Foreign Keys:
- enrollment_id REFERENCES enrollments(id)
- course_id REFERENCES courses(id)
- user_id REFERENCES users(id)

Indexes:
- UNIQUE INDEX idx_reviews_enrollment (enrollment_id)
- INDEX idx_reviews_course (course_id)
- INDEX idx_reviews_user (user_id)
- INDEX idx_reviews_deleted_at (deleted_at)

Notes:
- FK to `enrollments` enforces that only enrolled students can leave a review.
- UNIQUE on `enrollment_id` enforces one review per enrollment (implicitly one per user per course).
- `course_id` and `user_id` are kept as direct columns for query convenience, but are derivable from `enrollment_id`.
- The CHECK constraint on `rating` is enforced at database level (MySQL 8+ honors CHECK constraints).

---

# Table: menus

Represents navigation menu items that link to tabular listing pages for each entity in the schema.

| Column Name    | Type              | Constraints                                          |
|----------------|-------------------|------------------------------------------------------|
| id             | BIGINT            | PRIMARY KEY, AUTO_INCREMENT                          |
| parent_id      | BIGINT            | NULL, FOREIGN KEY (self-referencing)                 |
| label          | VARCHAR(120)      | NOT NULL                                             |
| entity_name    | VARCHAR(100)      | NULL                                                 |
| route_name     | VARCHAR(200)      | NULL                                                 |
| route_params   | JSON              | NULL DEFAULT NULL                                    |
| icon           | VARCHAR(100)      | NULL                                                 |
| position_order | INT               | NOT NULL DEFAULT 0                                   |
| visible        | TINYINT(1)        | NOT NULL DEFAULT 1                                   |
| created_at     | DATETIME          | NOT NULL DEFAULT CURRENT_TIMESTAMP                   |
| updated_at     | DATETIME          | NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP        |
| deleted        | TINYINT(1)        | NOT NULL DEFAULT 0                                   |
| deleted_at     | DATETIME          | NULL DEFAULT NULL                                    |

Foreign Keys:
- parent_id REFERENCES menus(id)

Indexes:
- INDEX idx_menus_parent (parent_id)
- INDEX idx_menus_position (position_order)
- INDEX idx_menus_visible (visible)
- INDEX idx_menus_deleted_at (deleted_at)

Notes:
- `parent_id` NULL = top-level menu item. Non-null = nested submenu entry.
- `entity_name` maps to a Doctrine entity (e.g. `User`, `Course`, `Lesson`) and drives which listing page is rendered.
- `route_name` is the Symfony route name for the listing page (e.g. `admin_users_index`). NULL if the item is a group/header with no direct link.
- `route_params` stores optional JSON parameters passed to the route (e.g. `{"status": "published"}`), allowing filtered listings from the same route.
- `icon` stores a CSS class or icon identifier (e.g. FontAwesome class `fa-users`).
- `visible = 0` hides the item without deleting it, useful for temporarily disabling menu entries.

Example data:

| id | parent_id | label          | entity_name    | route_name              | position_order |
|----|-----------|----------------|----------------|-------------------------|----------------|
| 1  | NULL      | Gestione       | NULL           | NULL                    | 1              |
| 2  | 1         | Utenti         | User           | admin_users_index       | 1              |
| 3  | 1         | Categorie      | Category       | admin_categories_index  | 2              |
| 4  | 1         | Corsi          | Course         | admin_courses_index     | 3              |
| 5  | 1         | Lezioni        | Lesson         | admin_lessons_index     | 4              |
| 6  | 1         | Iscrizioni     | Enrollment     | admin_enrollments_index | 5              |
| 7  | 1         | Progresso      | LessonProgress | admin_progress_index    | 6              |
| 8  | 1         | Recensioni     | Review         | admin_reviews_index     | 7              |

---

# Relationships Summary

## One-to-Many

- One category has many courses
- One instructor (user) has many courses
- One course has many lessons
- One course has many enrollments
- One course has many reviews
- One user has many reviews
- One enrollment has many lesson_progress records
- One menu item has many child menu items (self-referencing)

## Many-to-Many

- Users and courses are related through enrollments

## Derived from enrollment

- A review is only possible when an enrollment exists (FK enforced)
- lesson_progress is scoped to an enrollment

---

# Suggested Doctrine Entity Names

- User
- Category
- Course
- Lesson
- Enrollment
- LessonProgress
- Review
- Menu

---

# Suggested Symfony Conventions

- Use Doctrine ORM PHP attributes (`#[ORM\Entity]`, `#[ORM\Column]`, etc.)
- Use Repository classes for each entity with custom query methods
- Use Doctrine lifecycle callbacks or listeners for `created_at` / `updated_at` / `published_at` / `completed_at`
- Use Symfony Voters for role-based authorization (instructor-only course creation, student-only reviews)
- Apply `SoftDeleteable` behavior (e.g. via Gedmo/Doctrine Extensions) or implement manually with `deleted_at`
- Consider UUIDs instead of BIGINT ids for public-facing resources (courses, users)

---

# Suggested Future Improvements

Possible future tables:

- `payments` — purchase history linked to enrollments
- `certificates` — issued when enrollment.completed = 1
- `quizzes` / `quiz_attempts` — assessments per lesson or course
- `notifications` — in-app alerts for new lessons, reviews, completions
- `tags` / `course_tags` — many-to-many tagging for courses
- `coupons` / `coupon_usages` — discount codes for course purchases
