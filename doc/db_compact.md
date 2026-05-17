# Schema Database — Riferimento Compatto

## Tabelle e colonne

**users**: id, first_name, last_name, email, password_hash, role(student|instructor), created_at, updated_at, deleted, deleted_at

**categories**: id, name, description, created_at, deleted, deleted_at

**courses**: id, category_id→categories, instructor_id→users, title, slug, description, price(DECIMAL), level(beginner|intermediate|advanced), published(0|1), published_at, created_at, updated_at, deleted, deleted_at

**lessons**: id, course_id→courses, title, content, video_url, duration_minutes, position_order, created_at, deleted, deleted_at

**enrollments**: id, user_id→users, course_id→courses, enrolled_at, progress_percent(0-100), completed(0|1), completed_at, deleted, deleted_at

**lesson_progress**: id, enrollment_id→enrollments, lesson_id→lessons, completed_at, deleted, deleted_at

**reviews**: id, enrollment_id→enrollments, course_id→courses, user_id→users, rating(1-5 SMALLINT), comment, created_at, deleted, deleted_at

**menus**: id, parent_id→menus(nullable), label, entity_name, route_name, route_params(JSON), icon, position_order, visible(0|1), created_at, updated_at, deleted, deleted_at

## Relazioni chiave

- users(instructor) → courses (uno a molti)
- courses → lessons, enrollments, reviews (uno a molti)
- enrollments → lesson_progress, reviews (uno a molti)
- menus → menus tramite parent_id (self-referencing)

## Regole obbligatorie

- Filtra sempre i soft-delete: `WHERE <tabella>.deleted = 0`
- Solo SELECT: nessun INSERT, UPDATE, DELETE, DROP, ALTER
- Nessun LIMIT nelle query
