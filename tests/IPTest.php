<?php

use Arris\Toolkit\FireWall;

class IPTest extends \PHPUnit\Framework\TestCase
{
    private \Arris\Toolkit\FireWall $firewall;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->firewall = new \Arris\Toolkit\FireWall(deferred_range_sorting: true);
    }

    /**
     * @return void
     * @testdox Test white range over default black state
     */
    public function testWhiteRangeOverBlackDefault(): void
    {
        $whitelist = '192.168.0.40-192.168.0.50';
        $i = $this->firewall
            ->reset(/*\Arris\Toolkit\FireWall\FireWallState::FORBIDDEN*/ false)
            ->addWhiteList($whitelist);

        $equals = [
            '192.168.0.0'   =>  false,
            '192.168.0.1'   =>  false,
            '192.168.0.10'  =>  false,
            '192.168.0.42'  =>  true,
            '192.168.0.255' =>  false,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test white range over default white state
     */
    public function testWhiteRangeOverWhiteDefault(): void
    {
        $whitelist = '192.168.0.40-192.168.0.50';
        $i = $this->firewall
            ->reset(/*\Arris\Toolkit\FireWall\FireWallState::FORBIDDEN*/ true)
            ->addWhiteList($whitelist);

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.0.1'   =>  true,
            '192.168.0.10'  =>  true,
            '192.168.0.42'  =>  true,
            '192.168.0.255' =>  true,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test CIDR
     */
    public function testCIDR()
    {
        $whitelist = '192.168.0.0/24'; // 192.168.0.0 - 192.168.0.255
        $i = $this->firewall
            ->reset()
            ->addWhiteList($whitelist);

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.1.1'   =>  false,
            '192.168.0.10'  =>  true,
            '192.168.4.42'  =>  false,
            '192.168.0.255' =>  true,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test with mask
     */
    public function testMask()
    {
        $whitelist = '192.168.0.*'; // 192.168.0.0 - 192.168.0.255
        $i = $this->firewall
            ->reset()
            ->addWhiteList($whitelist);

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.1.1'   =>  false,
            '192.168.0.10'  =>  true,
            '192.168.4.42'  =>  false,
            '192.168.0.255' =>  true,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test single IP to matching
     */
    public function testEqual()
    {
        $whitelist = '192.168.0.0'; // 192.168.0.0 - 192.168.0.255
        $i = $this->firewall
            ->reset()
            ->addWhiteList($whitelist);

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.1.1'   =>  false,
            '192.168.0.10'  =>  false,
            '192.168.4.42'  =>  false,
            '192.168.0.255' =>  false,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test CIDR white list and Range black list
     */
    public function testBlackAndWhite()
    {
        $whitelist = '192.168.0.0/24'; // 192.168.0.0 - 192.168.0.255
        $blacklist = '192.168.0.10-192.168.0.50';

        $i = $this->firewall
            ->reset(false)
            ->addWhiteList($whitelist)
            ->addBlackList($blacklist)
        ;

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.0.1'   =>  true,
            '192.168.0.10'  =>  false,
            '192.168.0.42'  =>  false,
            '192.168.0.255' =>  true,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s} for {$whitelist} except {$blacklist}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test global blacklist exclude one IP
     */
    public function testBlackExcludeOneAllowed()
    {
        $i = $this->firewall
            ->reset(false)
            ->addWhiteList('192.168.0.42')
            /*->addBlackList('192.168.0.0/24')*/
        ;

        $equals = [
            '192.168.0.0'   =>  false,
            '192.168.0.1'   =>  false,
            '192.168.0.10'  =>  false,
            '192.168.0.42'  =>  true,
            '192.168.0.255' =>  false,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test global white exclude one IP blocked
     */
    public function testWhiteExcludeOneBlack()
    {
        $i = $this->firewall
            ->reset(true)
            ->addBlackList('192.168.0.42')
        ;

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.0.1'   =>  true,
            '192.168.0.10'  =>  true,
            '192.168.0.42'  =>  false,
            '192.168.0.255' =>  true,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test Five ranges (3 black 2 white) and IPs from different ranges
     */
    public function testFiveRanges()
    {
        $i = $this->firewall
            ->reset(true)
            ->addBlackList('192.168.0.0/16')            // 192.168.0.0 - 192.168.255.255
            ->addWhiteList('192.168.0.0/24')            // + 0-255
            ->addBlackList('192.168.0.10-192.168.0.80') // - 10-80
            ->addBlackList('192.168.0.100-192.168.0.121') // - 100-121
            ->addWhiteList('192.168.0.42')  // + 42
            ->addWhiteList('192.168.0.120') // + 120
            ->addBlackList('192.168.0.5')   // - 5
        ;

        $equals = [
            '192.168.0.0'   =>  true,
            '192.168.0.1'   =>  true,
            '192.168.0.10'  =>  false,
            '192.168.0.42'  =>  true,
            '192.168.0.99'  =>  true,
            '192.168.0.100' =>  false,
            '192.168.0.5'   =>  false,
            '192.168.0.200' =>  true,
            '192.168.0.70'  =>  false
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s}"
            );
        }
    }

    /**
     * @return void
     * @testdox Test IP not in range of rules, default state is forbidden
     */
    public function testIPNotInRange()
    {
        $i = $this->firewall
            ->reset(false)
            ->addWhiteList('192.168.0.0/24') // + 192.168.0.0 - 192.168.0.255
        ;

        $equals = [
            '10.16.4.2'     =>  false,
            '192.168.0.42'  =>  true,
            '192.168.44.1'  =>  false,
        ];

        foreach ($equals as $ip => $expected) {
            $actual = $i->validate($ip)->isAllowed();
            $actual_s = $actual ? 'TRUE' : 'FALSE';
            $this->assertEquals(
                $expected,
                $actual,
                "{$ip} is {$actual_s}"
            );
        }
    }

}