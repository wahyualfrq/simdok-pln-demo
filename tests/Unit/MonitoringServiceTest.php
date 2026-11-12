<?php

namespace Tests\Unit;

use App\Services\Google\GoogleSheetService;
use App\Services\MonitoringService;
use Mockery;
use Tests\TestCase;

class MonitoringServiceTest extends TestCase
{
    /**
     * Test cleanUnitName method strips numbering prefixes correctly.
     */
    public function test_clean_unit_name_removes_prefix(): void
    {
        $cacheServiceMock = Mockery::mock(\App\Services\CacheService::class);
        $syncServiceMock  = Mockery::mock(\App\Services\DriveMonitoringSyncService::class);
        
        $monitoringService = new MonitoringService($cacheServiceMock, $syncServiceMock);

        $this->assertEquals('MULP KENTEN', $monitoringService->cleanUnitName('a. MULP KENTEN'));
        $this->assertEquals('MULP SUKARAMI', $monitoringService->cleanUnitName('b.  MULP SUKARAMI'));
        $this->assertEquals('MULP RIVAI', $monitoringService->cleanUnitName('1. MULP RIVAI'));
        $this->assertEquals('MUP3 PALEMBANG', $monitoringService->cleanUnitName('MUP3 PALEMBANG'));
        $this->assertEquals('TL LAKDAN', $monitoringService->cleanUnitName('TL LAKDAN'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
