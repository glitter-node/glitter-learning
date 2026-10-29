<?php

namespace Modules\Glitter\Learning\Upgrades;

use App\Extension\AbstractUpgradeStep;

/**
 * 강좌 교육 설계 컬럼은 동봉된 신규 마이그레이션에서 추가합니다.
 * 기존 강좌 데이터의 변환이나 재계산은 수행하지 않습니다.
 *
 * @upgrade-path A
 */
class Upgrade_1_1_0 extends AbstractUpgradeStep {}
