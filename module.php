<?php

namespace Modules\Glitter\Learning;

use App\Extension\AbstractModule;
use Modules\Glitter\Learning\Listeners\AnonymizeLearningUserListener;
use Modules\Glitter\Learning\Listeners\CourseActivityLogListener;
use Modules\Glitter\Learning\Listeners\SearchLearningListener;

class Module extends AbstractModule
{
    public function getSearchProviders(): array
    {
        return [
            [
                'provider_id' => 'courses',
                'module_id' => 'glitter-learning',
                'search_type' => 'courses',
                'label' => ['ko' => '강좌', 'en' => 'Courses'],
                'description' => ['ko' => '공개 강좌 제목과 설명을 검색합니다.', 'en' => 'Search published course titles and descriptions.'],
                'enabled_by_default' => true,
                'default_order' => 30,
                'capabilities' => ['title', 'description'],
                'provider_version' => '1.0.0',
            ],
            [
                'provider_id' => 'lessons',
                'module_id' => 'glitter-learning',
                'search_type' => 'lessons',
                'label' => ['ko' => '차시', 'en' => 'Lessons'],
                'description' => ['ko' => '수강 권한이 있는 공개 차시를 검색합니다.', 'en' => 'Search published lessons available to the learner.'],
                'enabled_by_default' => true,
                'default_order' => 40,
                'capabilities' => ['title', 'body', 'access_controlled'],
                'provider_version' => '1.0.0',
            ],
        ];
    }

    public function getRoles(): array
    {
        return [[
            'identifier' => 'glitter-learning.manager',
            'name' => ['ko' => '학습 관리자', 'en' => 'Learning Manager'],
            'description' => ['ko' => '강좌와 학습 현황을 관리하는 역할', 'en' => 'Manages courses and learning records'],
        ]];
    }

    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => 'Glitter Learning', 'en' => 'Glitter Learning'],
            'description' => ['ko' => '학습 모듈 권한', 'en' => 'Learning module permissions'],
            'categories' => [
                [
                    'identifier' => 'courses',
                    'resource_route_key' => 'course',
                    'owner_key' => 'author_id',
                    'name' => ['ko' => '강좌 관리', 'en' => 'Course Management'],
                    'description' => ['ko' => '강좌와 차시 관리', 'en' => 'Manage courses and lessons'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '강좌 조회', 'en' => 'View Courses'], 'description' => ['ko' => '강좌와 차시 조회', 'en' => 'View courses and lessons'], 'type' => 'admin', 'roles' => ['admin', 'glitter-learning.manager']],
                        ['action' => 'create', 'name' => ['ko' => '강좌 생성', 'en' => 'Create Courses'], 'description' => ['ko' => '강좌 생성', 'en' => 'Create courses'], 'type' => 'admin', 'roles' => ['admin', 'glitter-learning.manager']],
                        ['action' => 'update', 'name' => ['ko' => '강좌 수정', 'en' => 'Update Courses'], 'description' => ['ko' => '강좌와 차시 수정', 'en' => 'Update courses and lessons'], 'type' => 'admin', 'roles' => ['admin', 'glitter-learning.manager']],
                        ['action' => 'delete', 'name' => ['ko' => '강좌 및 차시 삭제', 'en' => 'Delete Courses and Lessons'], 'description' => ['ko' => '학습 기록이 없는 강좌와 차시 삭제', 'en' => 'Delete courses and lessons without learning records'], 'type' => 'admin', 'roles' => ['admin']],
                    ],
                ],
                [
                    'identifier' => 'records',
                    'name' => ['ko' => '학습 기록', 'en' => 'Learning Records'],
                    'description' => ['ko' => '수강생, 진도와 수료 이력 조회', 'en' => 'View enrollments, progress, and completions'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '학습 기록 조회', 'en' => 'View Learning Records'], 'description' => ['ko' => '수강생과 수료 이력 조회', 'en' => 'View learners and completions'], 'type' => 'admin', 'roles' => ['admin', 'glitter-learning.manager']],
                    ],
                ],
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [[
            'name' => [
                'ko' => trans('glitter-learning::menu.course_management', locale: 'ko'),
                'en' => trans('glitter-learning::menu.course_management', locale: 'en'),
            ],
            'slug' => 'glitter-learning',
            'url' => '/admin/learning/courses',
            'icon' => 'fas fa-graduation-cap',
            'order' => 35,
            'permission' => 'glitter-learning.courses.read',
        ]];
    }

    public function getHookListeners(): array
    {
        return [AnonymizeLearningUserListener::class, CourseActivityLogListener::class, SearchLearningListener::class];
    }
}
