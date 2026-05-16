<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516210041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial schema: users, categories, courses, lessons, enrollments, lesson_progress, reviews, menus';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categories (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3AF346685E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courses (id BIGINT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, level ENUM(\'beginner\',\'intermediate\',\'advanced\') NOT NULL, published TINYINT DEFAULT 0 NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, category_id BIGINT NOT NULL, instructor_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_A9A55A4C989D9B62 (slug), INDEX IDX_A9A55A4C12469DE2 (category_id), INDEX IDX_A9A55A4C8C4FC193 (instructor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE enrollments (id BIGINT AUTO_INCREMENT NOT NULL, enrolled_at DATETIME NOT NULL, progress_percent INT DEFAULT 0 NOT NULL, completed TINYINT DEFAULT 0 NOT NULL, completed_at DATETIME DEFAULT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, user_id BIGINT NOT NULL, course_id BIGINT NOT NULL, INDEX IDX_CCD8C132A76ED395 (user_id), INDEX IDX_CCD8C132591CC992 (course_id), UNIQUE INDEX idx_enrollment_unique (user_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lesson_progress (id BIGINT AUTO_INCREMENT NOT NULL, completed_at DATETIME NOT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, enrollment_id BIGINT NOT NULL, lesson_id BIGINT NOT NULL, INDEX IDX_6A46B85F8F7DB25B (enrollment_id), INDEX IDX_6A46B85FCDF80196 (lesson_id), UNIQUE INDEX idx_lesson_progress_unique (enrollment_id, lesson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lessons (id BIGINT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, video_url VARCHAR(500) DEFAULT NULL, duration_minutes INT DEFAULT 0 NOT NULL, position_order INT NOT NULL, created_at DATETIME NOT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, course_id BIGINT NOT NULL, INDEX IDX_3F4218D9591CC992 (course_id), UNIQUE INDEX idx_lessons_course_position (course_id, position_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menus (id BIGINT AUTO_INCREMENT NOT NULL, label VARCHAR(120) NOT NULL, entity_name VARCHAR(100) DEFAULT NULL, route_name VARCHAR(200) DEFAULT NULL, route_params JSON DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, position_order INT DEFAULT 0 NOT NULL, visible TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, parent_id BIGINT DEFAULT NULL, INDEX IDX_727508CF727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reviews (id BIGINT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, enrollment_id BIGINT NOT NULL, course_id BIGINT NOT NULL, user_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_6970EB0F8F7DB25B (enrollment_id), INDEX IDX_6970EB0F591CC992 (course_id), INDEX IDX_6970EB0FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BIGINT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role ENUM(\'student\',\'instructor\') NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted TINYINT NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_A9A55A4C12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_A9A55A4C8C4FC193 FOREIGN KEY (instructor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE enrollments ADD CONSTRAINT FK_CCD8C132A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE enrollments ADD CONSTRAINT FK_CCD8C132591CC992 FOREIGN KEY (course_id) REFERENCES courses (id)');
        $this->addSql('ALTER TABLE lesson_progress ADD CONSTRAINT FK_6A46B85F8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollments (id)');
        $this->addSql('ALTER TABLE lesson_progress ADD CONSTRAINT FK_6A46B85FCDF80196 FOREIGN KEY (lesson_id) REFERENCES lessons (id)');
        $this->addSql('ALTER TABLE lessons ADD CONSTRAINT FK_3F4218D9591CC992 FOREIGN KEY (course_id) REFERENCES courses (id)');
        $this->addSql('ALTER TABLE menus ADD CONSTRAINT FK_727508CF727ACA70 FOREIGN KEY (parent_id) REFERENCES menus (id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollments (id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F591CC992 FOREIGN KEY (course_id) REFERENCES courses (id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses DROP FOREIGN KEY FK_A9A55A4C12469DE2');
        $this->addSql('ALTER TABLE courses DROP FOREIGN KEY FK_A9A55A4C8C4FC193');
        $this->addSql('ALTER TABLE enrollments DROP FOREIGN KEY FK_CCD8C132A76ED395');
        $this->addSql('ALTER TABLE enrollments DROP FOREIGN KEY FK_CCD8C132591CC992');
        $this->addSql('ALTER TABLE lesson_progress DROP FOREIGN KEY FK_6A46B85F8F7DB25B');
        $this->addSql('ALTER TABLE lesson_progress DROP FOREIGN KEY FK_6A46B85FCDF80196');
        $this->addSql('ALTER TABLE lessons DROP FOREIGN KEY FK_3F4218D9591CC992');
        $this->addSql('ALTER TABLE menus DROP FOREIGN KEY FK_727508CF727ACA70');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F8F7DB25B');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F591CC992');
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0FA76ED395');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE courses');
        $this->addSql('DROP TABLE enrollments');
        $this->addSql('DROP TABLE lesson_progress');
        $this->addSql('DROP TABLE lessons');
        $this->addSql('DROP TABLE menus');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
