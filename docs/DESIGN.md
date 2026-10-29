# Glitter Learning v1.0.0 design

## Domain rules

- Only published courses whose publication time has arrived are visible through learner APIs.
- Lesson bodies require authentication and an active or completed enrollment owned by the requester.
- `(course_id, user_id)`, `(enrollment_id, lesson_id)`, and `enrollment_id` on completions are database-level uniqueness boundaries.
- Progress is completed required lessons divided by every required lesson in the course. An unpublished required lesson therefore prevents completion until it is published and completed.
- A course with no required lessons never completes automatically.
- Completion records retain their criteria version and required lesson ID snapshot and are never recalculated or revoked.
- Courses with enrollment records cannot be physically deleted because of a restrictive foreign key; the supported lifecycle is archival.
- Lessons with progress records cannot be deleted and must be made private instead.
- On physical user deletion, author and learner identifiers are nulled while aggregate learning and completion history is retained.

## Security boundaries

- Administrator APIs use Gnuboard7 permission middleware.
- Learner writes derive the user from the authenticated request and never accept a user ID.
- Course and lesson ownership is verified again in services/controllers.
- Public endpoints never serialize lesson bodies. The lesson endpoint checks publication, course publication, and enrollment ownership.
