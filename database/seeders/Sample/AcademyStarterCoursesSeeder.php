<?php

namespace Modules\Glitter\Learning\Database\Seeders\Sample;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\Learning\Models\Course;
use Modules\Glitter\Learning\Models\Lesson;
use RuntimeException;

/**
 * Glitter Learning 초기 공개 강좌 6개와 차시 26개를 생성한다.
 *
 * 강좌·차시만 생성하며 회원·수강·진도·수료 데이터는 만들지 않는다.
 * 기존 slug 또는 lesson 데이터가 다른 경우 안전을 위해 예외를 발생시켜 중단한다.
 */
final class AcademyStarterCoursesSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @param array<string, int> $counts */
    public function setSeederCounts(array $counts): void
    {
        $this->counts = $counts;
    }

    public function run(): void
    {
        $authorId = $this->counts['author_id'] ?? 0;
        if ($authorId < 1 || ! User::query()->whereKey($authorId)->exists()) {
            throw new RuntimeException('A valid --count=author_id=<user-id> is required for starter courses.');
        }

        $this->command?->info('Preparing 6 starter courses and 26 lessons; existing records will be checked and preserved.');

        $createdCourses = 0;
        $createdLessons = 0;

        DB::transaction(function () use ($authorId, &$createdCourses, &$createdLessons): void {
            foreach ($this->courses() as $courseData) {
                [$courseCreated, $lessonsCreated] = $this->seedCourse($courseData, $authorId);
                $createdCourses += $courseCreated;
                $createdLessons += $lessonsCreated;
            }
        });

        $this->command?->info("Glitter Learning starter courses ready: courses={$createdCourses}, lessons={$createdLessons}");
    }

    /** @return array<int, array<string, mixed>> */
    private function courses(): array
    {
        return [
            [
                'title' => '온라인 학습 시작하기',
                'slug' => 'glitter-learning-start',
                'introduction' => 'Academy에서 강좌를 찾고 수강부터 수료까지 학습 흐름을 익힙니다.',
                'description' => "이 강좌에서는 온라인 학습의 전체 흐름을 직접 살펴봅니다.\n\n수강 신청, 차시 완료, 진도율, 수료 조건을 이해하면 어떤 강좌도 더 자신 있게 시작할 수 있습니다.",
                'lessons' => [
                    ['title' => 'Academy와 강좌 살펴보기', 'required' => true, 'body' => "학습 목표\n- Academy의 강좌 목록과 상세 화면을 이해합니다.\n\n도입\n온라인 학습은 원하는 시간에 필요한 내용을 반복해서 학습할 수 있다는 장점이 있습니다. 먼저 공개 강좌 목록에서 제목과 소개를 비교하고, 상세 화면에서 차시 구성을 확인합니다.\n\n핵심 개념\n강좌는 여러 차시로 구성되며, 필수 차시와 선택 차시가 구분될 수 있습니다. 공개된 강좌만 일반 회원에게 표시됩니다.\n\n실습\n강좌 목록에서 관심 있는 강좌 하나를 열고 소개, 차시 수, 필수 여부를 확인해 보세요.\n\n핵심 정리\n강좌 상세 화면은 학습을 시작하기 전 목표와 커리큘럼을 확인하는 출발점입니다."],
                    ['title' => '수강 신청과 내 학습실', 'required' => true, 'body' => "학습 목표\n- 수강 신청 상태와 내 학습실의 역할을 설명할 수 있습니다.\n\n도입\n수강 신청을 하면 학습 기록이 회원 계정에 연결됩니다. 이후 내 학습실에서 현재 수강 중인 강좌와 수료한 강좌를 한곳에서 확인합니다.\n\n핵심 개념\n수강 상태는 학습 중, 수료, 취소 등으로 관리됩니다. 같은 회원이 같은 강좌를 중복 신청하지 않도록 등록이 보호됩니다.\n\n실습\n공개 강좌의 수강 신청 버튼을 눌러 등록하고 내 학습실에서 강좌가 나타나는지 확인해 보세요.\n\n핵심 정리\n내 학습실은 학습을 이어 가고 완료 기록을 확인하는 개인 대시보드입니다."],
                    ['title' => '차시 학습과 진도 기록', 'required' => true, 'body' => "학습 목표\n- 차시 완료와 필수 차시 진도율의 관계를 이해합니다.\n\n도입\n온라인 학습에서는 내용을 읽는 것뿐 아니라 완료 동작을 통해 학습 기록을 남깁니다. 서버가 완료 요청을 처리하므로 반복 요청에도 같은 차시 기록이 중복되지 않습니다.\n\n핵심 개념\n진도율은 전체 필수 차시 중 완료한 필수 차시의 비율입니다. 선택 차시는 학습에는 도움이 되지만 필수 진도율 계산에는 포함되지 않습니다.\n\n실습\n첫 번째 필수 차시를 완료한 뒤 진도율이 어떻게 변하는지 확인하고, 다른 차시를 이어서 학습해 보세요.\n\n핵심 정리\n완료 버튼은 서버에 학습 사실을 기록하고, 진도율은 필수 차시 기준으로 계산됩니다."],
                    ['title' => '수료 조건과 학습 성취 확인', 'required' => false, 'body' => "학습 목표\n- 수료 조건과 수료 기록의 의미를 설명할 수 있습니다.\n\n도입\n수료는 학습 과정에서 달성한 결과를 보존하는 기록입니다. 강좌가 나중에 수정되더라도 수료 당시의 기준과 필수 차시 수가 기록으로 남습니다.\n\n핵심 개념\n모든 필수 차시를 완료하면 서버가 수료를 판정합니다. 필수 차시가 없는 강좌는 자동 수료 대상이 아닙니다.\n\n실습\n강좌의 필수 차시 수를 확인하고, 완료 후 내 수료 내역에서 기록을 찾아보세요.\n\n핵심 정리\n수료는 한 번만 생성되며 수료 당시의 기준을 보존합니다."],
                ],
            ],
            [
                'title' => 'HTML과 CSS 기초',
                'slug' => 'html-css-basics',
                'introduction' => '웹 문서의 구조와 스타일을 만드는 HTML·CSS의 핵심을 익힙니다.',
                'description' => "웹 페이지가 어떻게 구성되고 표현되는지 기초부터 실습합니다.\n\n문서 구조를 먼저 만들고 선택자와 레이아웃을 적용하는 순서로 작은 소개 페이지를 완성합니다.",
                'lessons' => [
                    ['title' => '웹 페이지의 기본 구조', 'required' => true, 'body' => "학습 목표\n- HTML 문서의 기본 구조를 설명합니다.\n\n도입\n웹 브라우저는 HTML을 읽어 문서의 구조를 만들고 CSS를 적용해 화면을 그립니다. 구조와 표현을 분리하면 유지보수가 쉬워집니다.\n\n핵심 개념\n문서는 doctype, html, head, body 영역으로 나뉩니다. head에는 제목과 메타 정보가, body에는 사용자에게 보일 내용이 들어갑니다.\n\n실습\n제목과 한 문단을 가진 최소 HTML 문서의 구조를 손으로 작성해 보세요.\n\n핵심 정리\nHTML은 문서의 의미와 구조를 표현하는 언어입니다."],
                    ['title' => 'HTML 요소와 문서 작성', 'required' => true, 'body' => "학습 목표\n- 자주 사용하는 HTML 요소의 역할을 구분합니다.\n\n도입\n요소는 여는 태그와 닫는 태그, 그리고 속성으로 문서의 의미를 표현합니다.\n\n핵심 개념\n제목은 h1~h6, 문단은 p, 목록은 ul·ol·li, 링크는 a, 이미지는 img 요소를 사용합니다. 의미에 맞는 요소를 선택하면 접근성과 검색 이해도가 좋아집니다.\n\n실습\n자기소개 문서에 제목, 소개 문단, 관심사 목록, 연락처 링크를 추가해 보세요.\n\n핵심 정리\nHTML 요소는 보이는 모양보다 콘텐츠의 의미를 먼저 표현해야 합니다."],
                    ['title' => 'CSS 선택자와 스타일', 'required' => true, 'body' => "학습 목표\n- 기본 선택자로 요소에 스타일을 적용합니다.\n\n도입\nCSS는 선택한 요소에 색상, 글꼴, 여백 등 표현 규칙을 적용합니다.\n\n핵심 개념\n요소 선택자, 클래스 선택자, 아이디 선택자를 이해하고, 더 구체적인 선택자가 우선되는 원리를 익힙니다.\n\n실습\n소개 페이지의 제목 색상, 문단 간격, 링크 상태를 CSS로 바꿔 보세요.\n\n핵심 정리\n재사용할 스타일에는 클래스를 사용하고 전역 요소 선택자는 신중하게 사용합니다."],
                    ['title' => '박스 모델과 레이아웃', 'required' => true, 'body' => "학습 목표\n- 박스 모델과 기본 레이아웃 방식을 설명합니다.\n\n도입\n모든 요소는 콘텐츠, 패딩, 테두리, 마진으로 이루어진 상자처럼 배치됩니다.\n\n핵심 개념\nbox-sizing, display, flex, grid를 사용하면 요소 간 크기와 정렬을 예측하기 쉬워집니다.\n\n실습\n카드 세 개를 가로로 배치한 뒤 좁은 화면에서는 한 열로 바꿔 보세요.\n\n핵심 정리\n반응형 레이아웃은 고정 너비보다 유연한 그리드와 간격 설계가 중요합니다."],
                    ['title' => '간단한 소개 페이지 만들기', 'required' => true, 'body' => "학습 목표\n- HTML과 CSS를 조합해 작은 페이지를 완성합니다.\n\n도입\n앞에서 익힌 구조와 스타일을 하나의 결과물로 연결합니다.\n\n핵심 개념\n콘텐츠 구조를 먼저 작성하고 공통 색상, 간격, 반응형 규칙을 단계적으로 적용합니다.\n\n실습\n제목, 자기소개, 관심사 카드, 연락 링크가 있는 소개 페이지를 완성하고 320px 화면에서도 가로 스크롤이 없는지 확인하세요.\n\n핵심 정리\n작은 페이지도 의미 있는 HTML과 일관된 CSS 토큰으로 설계하면 확장하기 쉽습니다."],
                ],
            ],
            [
                'title' => 'JavaScript 첫걸음',
                'slug' => 'javascript-first-steps',
                'introduction' => '브라우저에서 동작하는 JavaScript의 기본 문법과 상호작용을 연습합니다.',
                'description' => "JavaScript가 웹 페이지에 동작과 상태를 더하는 방식을 익힙니다.\n\n작은 함수와 이벤트부터 DOM 변경까지 단계적으로 실습합니다.",
                'lessons' => [
                    ['title' => 'JavaScript의 역할', 'required' => true, 'body' => "학습 목표\n- HTML, CSS, JavaScript의 역할을 구분합니다.\n\n도입\nHTML이 구조, CSS가 표현을 담당한다면 JavaScript는 사용자 동작에 반응하는 로직을 담당합니다.\n\n핵심 개념\n스크립트는 입력을 읽고 조건을 판단한 뒤 화면이나 데이터 상태를 변경합니다.\n\n실습\n버튼 클릭 시 안내 문구를 바꾸는 동작을 의사 코드로 작성해 보세요.\n\n핵심 정리\nJavaScript는 웹 페이지를 정적인 문서에서 상호작용 가능한 애플리케이션으로 확장합니다."],
                    ['title' => '변수와 자료형', 'required' => true, 'body' => "학습 목표\n- 변수 선언과 기본 자료형을 사용합니다.\n\n도입\n프로그램은 값을 저장하고 다시 사용하면서 상태를 관리합니다.\n\n핵심 개념\nconst와 let의 차이, 문자열·숫자·불리언·배열·객체의 특징을 익힙니다.\n\n실습\n학습자 이름과 완료한 차시 수를 변수에 저장하고 문장으로 조합해 보세요.\n\n핵심 정리\n변수 이름은 저장한 의미가 드러나도록 정하고, 변경되지 않는 값에는 const를 우선 사용합니다."],
                    ['title' => '조건문과 반복문', 'required' => true, 'body' => "학습 목표\n- 조건에 따라 분기하고 목록을 반복 처리합니다.\n\n도입\n프로그램의 흐름은 조건과 반복으로 표현할 수 있습니다.\n\n핵심 개념\nif·else로 조건을 나누고 for·for...of로 여러 값을 순회합니다.\n\n실습\n필수 차시 완료 수가 전체와 같으면 수료 메시지를 출력하고, 아니면 남은 차시 수를 계산해 보세요.\n\n핵심 정리\n복잡한 분기는 작은 조건으로 나누고 반복 대상의 의미를 명확히 유지합니다."],
                    ['title' => '함수와 이벤트', 'required' => true, 'body' => "학습 목표\n- 반복 로직을 함수로 묶고 이벤트를 처리합니다.\n\n도입\n함수는 작업에 이름을 부여해 재사용과 테스트를 쉽게 합니다.\n\n핵심 개념\n매개변수와 반환값, 클릭·입력 이벤트의 차이, 이벤트 핸들러의 역할을 익힙니다.\n\n실습\n진도율을 계산하는 함수와 완료 버튼 클릭 핸들러의 책임을 나누어 설계해 보세요.\n\n핵심 정리\n이벤트에서는 입력을 검증하고 필요한 상태 변경만 수행하는 것이 안전합니다."],
                    ['title' => 'DOM을 활용한 화면 변경', 'required' => true, 'body' => "학습 목표\n- DOM 요소를 선택하고 안전하게 화면을 변경합니다.\n\n도입\n브라우저는 HTML을 DOM 트리로 표현하며 JavaScript는 이 트리를 읽고 변경할 수 있습니다.\n\n핵심 개념\nquerySelector, textContent, classList를 사용해 내용을 바꾸고 스타일 상태를 전환합니다.\n\n실습\n완료 버튼을 누르면 상태 배지의 텍스트와 클래스를 변경해 보세요. 사용자 입력을 innerHTML에 그대로 넣지 않는 이유도 확인합니다.\n\n핵심 정리\nDOM 변경은 필요한 노드만 대상으로 하고 외부 입력은 안전한 텍스트 처리 방식을 사용합니다."],
                ],
            ],
            [
                'title' => 'Git과 협업 기초',
                'slug' => 'git-collaboration-basics',
                'introduction' => '변경 이력을 안전하게 관리하고 협업 흐름의 기본을 익힙니다.',
                'description' => "Git의 핵심 개념을 익히고 개인 작업을 팀의 공유 저장소와 연결하는 흐름을 연습합니다.",
                'lessons' => [
                    ['title' => '버전 관리 이해하기', 'required' => true, 'body' => "학습 목표\n- 버전 관리가 필요한 이유를 설명합니다.\n\n도입\n파일을 직접 복사해 보관하면 변경 이유와 복구 지점을 찾기 어렵습니다.\n\n핵심 개념\n버전 관리는 변경 묶음, 작성자, 시점, 설명을 기록해 실수를 되돌리고 작업을 비교할 수 있게 합니다.\n\n실습\n프로젝트에서 되돌리고 싶은 변경 사례를 세 가지 적어 보세요.\n\n핵심 정리\n작은 단위로 의미 있는 변경을 기록하면 협업 비용이 줄어듭니다."],
                    ['title' => '저장소와 커밋', 'required' => true, 'body' => "학습 목표\n- 저장소와 커밋의 관계를 이해합니다.\n\n도입\n저장소는 프로젝트와 이력을 함께 관리하는 공간이며 커밋은 하나의 논리적인 변경 기록입니다.\n\n핵심 개념\n작업 트리, 스테이징 영역, 커밋의 흐름을 구분하고 메시지에 변경 의도를 담습니다.\n\n실습\n문서 수정과 기능 수정을 별도 커밋으로 나누는 기준을 세워 보세요.\n\n핵심 정리\n커밋은 나중에 읽을 동료에게 보내는 짧은 설명이기도 합니다."],
                    ['title' => '브랜치와 병합', 'required' => true, 'body' => "학습 목표\n- 브랜치로 작업을 격리하고 병합합니다.\n\n도입\n기능별 브랜치는 안정적인 기본 브랜치를 보호하면서 독립적인 작업을 가능하게 합니다.\n\n핵심 개념\n브랜치 포인터, 병합, 충돌, 충돌 해결 후 검토 과정을 익힙니다.\n\n실습\n두 사람이 같은 줄을 수정했을 때 충돌을 확인하고 의도에 맞는 결과를 선택하는 절차를 적어 보세요.\n\n핵심 정리\n병합 전에는 변경 내용을 검토하고 충돌 해결 결과를 다시 확인해야 합니다."],
                    ['title' => '협업 작업 흐름', 'required' => true, 'body' => "학습 목표\n- 이슈부터 리뷰까지의 기본 협업 흐름을 설명합니다.\n\n도입\n협업은 코드 작성만이 아니라 요구사항 공유, 리뷰, 기록, 배포를 포함합니다.\n\n핵심 개념\n작업 단위 정의, 브랜치 생성, 커밋, 리뷰 요청, 피드백 반영, 병합 순서를 익힙니다.\n\n실습\n작은 기능 하나를 이슈 제목과 완료 조건으로 나누어 작성해 보세요.\n\n핵심 정리\n명확한 기록과 짧은 피드백 주기가 건강한 협업을 만듭니다."],
                ],
            ],
            [
                'title' => '웹 보안 기초',
                'slug' => 'web-security-basics',
                'introduction' => '인증, 권한, 입력 검증 등 웹 서비스 보안의 기본 원칙을 익힙니다.',
                'description' => "웹 서비스에서 자주 마주치는 보안 개념을 개발 흐름에 연결합니다.\n\n공격 기법을 실행하지 않고 방어 설계와 안전한 운영 습관에 집중합니다.",
                'lessons' => [
                    ['title' => '웹 보안의 기본 개념', 'required' => true, 'body' => "학습 목표\n- 기밀성·무결성·가용성의 의미를 설명합니다.\n\n도입\n웹 보안은 특정 도구 하나가 아니라 서비스 전반의 위험을 줄이는 설계 원칙입니다.\n\n핵심 개념\n자산, 위협, 취약점, 위험을 구분하고 최소 권한과 안전한 기본값을 적용합니다.\n\n실습\n온라인 학습 서비스의 보호 대상(회원 정보, 학습 기록, 관리자 기능)을 목록으로 정리해 보세요.\n\n핵심 정리\n보안은 기능을 완성한 뒤 추가하는 장식이 아니라 설계 단계부터 포함해야 합니다."],
                    ['title' => '인증과 권한', 'required' => true, 'body' => "학습 목표\n- 인증과 권한 부여의 차이를 구분합니다.\n\n도입\n로그인했다는 사실과 특정 작업을 할 수 있다는 사실은 서로 다릅니다.\n\n핵심 개념\n인증은 사용자를 확인하고, 권한은 확인된 사용자가 수행할 수 있는 작업을 판정합니다. 객체 단위 접근 검사도 필요합니다.\n\n실습\n관리자 강좌 수정과 일반 회원 차시 완료에 필요한 조건을 각각 적어 보세요.\n\n핵심 정리\n클라이언트 화면의 숨김만으로 권한을 보호할 수 없으며 서버 검사가 최종 방어선입니다."],
                    ['title' => '입력값 검증과 출력 안전성', 'required' => true, 'body' => "학습 목표\n- 입력 검증과 출력 인코딩의 목적을 설명합니다.\n\n도입\n외부에서 들어오는 값은 신뢰할 수 없으므로 저장·처리·출력 단계마다 안전성을 확인해야 합니다.\n\n핵심 개념\n허용 목록 검증, 길이 제한, 문맥에 맞는 출력 인코딩, 안전한 쿼리 바인딩을 익힙니다.\n\n실습\n강좌 제목과 본문을 받을 때 적용할 검증 규칙과 HTML 출력 시 주의점을 작성해 보세요.\n\n핵심 정리\n검증은 데이터의 기대 형태를 보장하고 인코딩은 출력 문맥에서 해석을 안전하게 만듭니다."],
                    ['title' => '안전한 웹 서비스 운영 습관', 'required' => true, 'body' => "학습 목표\n- 운영 단계에서 지켜야 할 보안 습관을 정리합니다.\n\n도입\n배포 후에도 권한 검토, 로그 확인, 의존성 업데이트, 백업 점검이 계속 필요합니다.\n\n핵심 개념\n비밀정보를 코드에 저장하지 않고, 최소 권한 계정과 HTTPS, 오류 정보 최소화, 변경 기록을 사용합니다.\n\n실습\n월간 운영 점검표에 접근 권한, 백업 복구, 보안 업데이트, 모니터링 항목을 추가해 보세요.\n\n핵심 정리\n안전한 운영은 반복 가능한 점검 절차와 책임 있는 변경 관리에서 시작합니다."],
                ],
            ],
            [
                'title' => '효과적인 온라인 학습 습관',
                'slug' => 'effective-online-learning',
                'introduction' => '목표를 세우고 시간을 관리하며 배운 내용을 오래 기억하는 방법을 익힙니다.',
                'description' => "온라인 학습을 지속 가능한 습관으로 만드는 방법을 단계별로 정리합니다.\n\n작은 목표와 기록을 통해 학습 성취를 확인하고 다음 행동으로 연결합니다.",
                'lessons' => [
                    ['title' => '학습 목표 설정', 'required' => true, 'body' => "학습 목표\n- 측정 가능한 학습 목표를 작성합니다.\n\n도입\n목표가 모호하면 무엇을 공부해야 하는지와 언제 끝나는지 판단하기 어렵습니다.\n\n핵심 개념\n주제, 결과, 기한, 확인 방법을 포함해 목표를 구체화합니다.\n\n실습\n이번 주에 완료할 차시와 완료 기준을 한 문장으로 작성해 보세요.\n\n핵심 정리\n좋은 목표는 학습 행동과 완료 여부를 함께 보여 줍니다."],
                    ['title' => '학습 시간 계획', 'required' => true, 'body' => "학습 목표\n- 현실적인 학습 계획을 세웁니다.\n\n도입\n긴 시간을 한 번에 확보하기보다 반복 가능한 짧은 시간을 만드는 편이 지속하기 쉽습니다.\n\n핵심 개념\n주간 가용 시간, 차시 난이도, 휴식, 예비 시간을 함께 고려합니다.\n\n실습\n이번 주 학습 시간을 달력에 배치하고 예상보다 늦어질 때의 대안을 정해 보세요.\n\n핵심 정리\n계획은 지키지 못했을 때 수정할 수 있어야 합니다."],
                    ['title' => '복습과 지식 정리', 'required' => true, 'body' => "학습 목표\n- 복습 주기와 지식 정리 방법을 선택합니다.\n\n도입\n새로 배운 내용은 적절한 간격으로 다시 꺼내 볼 때 오래 기억됩니다.\n\n핵심 개념\n짧은 회상, 핵심 문장 작성, 예제 재현, 질문 만들기를 활용합니다.\n\n실습\n한 차시를 세 문장으로 요약하고, 이해가 부족한 부분에 질문을 하나 남겨 보세요.\n\n핵심 정리\n복습은 다시 읽기보다 스스로 설명하고 적용하는 활동에 가깝습니다."],
                    ['title' => '학습 성취 기록과 다음 목표', 'required' => true, 'body' => "학습 목표\n- 학습 결과를 기록하고 다음 목표로 연결합니다.\n\n도입\n성취를 기록하면 무엇을 해냈는지 확인하고 다음 학습의 출발점을 정할 수 있습니다.\n\n핵심 개념\n완료한 차시, 어려웠던 점, 적용한 사례, 다음 행동을 짧게 남깁니다.\n\n실습\n이번 강좌에서 배운 내용을 실제 작업에 적용할 한 가지 후속 목표를 작성해 보세요.\n\n핵심 정리\n학습 기록은 과거를 보관하는 동시에 다음 성장 방향을 정하는 도구입니다."],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $courseData @return array{0:int,1:int} */
    private function seedCourse(array $courseData, int $authorId): array
    {
        /** @var Course|null $course */
        $course = Course::query()->where('slug', $courseData['slug'])->lockForUpdate()->first();
        $createdCourse = 0;

        if ($course === null) {
            $course = Course::query()->create([
                'title' => $courseData['title'],
                'slug' => $courseData['slug'],
                'introduction' => $courseData['introduction'],
                'description' => $courseData['description'],
                'author_id' => $authorId,
                'status' => 'published',
                'published_at' => now(),
            ]);
            $createdCourse = 1;
        } elseif ($course->title !== $courseData['title'] || (int) $course->author_id !== $authorId) {
            throw new RuntimeException("Starter slug already belongs to different course data: {$courseData['slug']}");
        } elseif ($course->status !== 'published' || $course->published_at === null) {
            throw new RuntimeException("Starter course is not publicly available; refusing to change it: {$courseData['slug']}");
        }

        $existing = $course->lessons()->lockForUpdate()->get()->keyBy('title');
        $expectedTitles = collect($courseData['lessons'])->pluck('title');
        if ($existing->keys()->diff($expectedTitles)->isNotEmpty()) {
            throw new RuntimeException("Starter course contains unexpected lessons: {$courseData['slug']}");
        }

        $createdLessons = 0;
        foreach ($courseData['lessons'] as $index => $lessonData) {
            /** @var Lesson|null $lesson */
            $lesson = $existing->get($lessonData['title']);
            $expectedOrder = $index + 1;
            if ($lesson !== null) {
                $matches = (int) $lesson->sort_order === $expectedOrder
                    && (bool) $lesson->is_required === $lessonData['required']
                    && (bool) $lesson->is_published
                    && (string) ($lesson->body ?? '') === $lessonData['body'];
                if (! $matches) {
                    throw new RuntimeException("Existing starter lesson differs: {$courseData['slug']} / {$lessonData['title']}");
                }
                continue;
            }

            Lesson::query()->create([
                'course_id' => $course->id,
                'title' => $lessonData['title'],
                'body' => $lessonData['body'],
                'media_reference' => null,
                'sort_order' => $expectedOrder,
                'is_required' => $lessonData['required'],
                'is_published' => true,
            ]);
            $createdLessons++;
        }

        return [$createdCourse, $createdLessons];
    }
}
