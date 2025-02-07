<?php

namespace Arris\Toolkit;

class FireWall
{
    private array $white_list;
    private array $black_list;

    private array $sorted_ranges;

    public bool $allowed = false;
    public bool $forbidden = true;
    public bool $default_state = false;

    public function __construct($defaultState = false)
    {
        $this->white_list = [];
        $this->black_list = [];
    }

    public function reset():FireWall
    {}

    public function setDefaultState(bool $allowed = false)
    {
        if ($allowed) {
            $this->addWhiteList('*.*.*.*');
        } else {
            $this->addBlackList('*.*.*.*');
        }
    }

    public function addWhiteList($list)
    {
        // можно вычислять мощность диапазона сразу при добавлении
        // добавлять в sorted_range
        // и сразу сортировать
        // тогда validate() будет только искать
    }

    public function addBlackList($list){}

    public function validate($ip = null)
    {
        if (is_null($ip)) {
            $ip = $this->getIP();

            // всё еще может быть ошибка получения IP, значит он невалиден
            if (is_null($ip)) {
                return $this;
            }
        }

        // теперь нам нужно отсортировать списки (если это еще не сделано)
        $this->sortRanges();

        // потом найти диапазон
        $found_range_definition = $this->findShortedRange($ip);

        if (empty($found_range_definition)) {
            // диапазон не найден, то есть айпишник регулируется правилом по-умолчанию для списка '*.*.*.*.'
            $this->allowed = $this->default_state;
        } else {
            $this->allowed = $found_range_definition['type'] === 'white';
            $this->forbidden = !$this->allowed;
        }

        return $this;
    }

    public function isAllowed():bool
    {
        return $this->allowed;
    }

    public function isForbidden():bool
    {
        return $this->forbidden;
    }

    /**
     * Сортирует диапазоны перед поиском
     *
     * @return void
     */
    private function sortRanges(): void
    {
        foreach ($this->white_list as $range) {
            $this->sorted_ranges[] = [
                'range'     =>  $range,
                'type'      =>  'white',
                'capacity'  =>  $this->getRangeCapacity($range)
            ];
        }

        foreach ($this->black_list as $range) {
            $this->sorted_ranges[] = [
                'range'     =>  $range,
                'type'      =>  'black',
                'capacity'  =>  $this->getRangeCapacity($range)
            ];
        }

        usort($this->sorted_ranges, function ($left, $right){
            return $right['capacity'] - $left['capacity'];
        });
    }

    /**
     * Вычисляет мощность диапазона адресов
     *
     * @param $range
     * @return int
     */
    private function getRangeCapacity($range):int
    {
        if (empty($range)) {
            return 0;
        }

        if ($range === '*') {
            return 4_294_967_296;
        }

        // Обработка CIDR-нотации
        if (str_contains($range, '/')) {
            list($ip, $prefix) = explode('/', $range);
            return (int)pow(2, 32 - (int)$prefix);
        }

        // Обработка диапазона '192.168.1.20-192.168.1.40'
        if (str_contains($range, '-')) {
            list($start, $end) = explode('-', $range);
            return (int)ip2long($end) - (int)ip2long($start) + 1;
        }

        // Одиночный айпишник или строка с маской *
        return match (substr_count('*', $range)) {
            4   =>  4_294_967_296,
            3   =>  16_777_216,
            2   =>  65_636,
            1   =>  256,
            // маски нет, одиночный айпишник
            default =>  1
        };
    }

    /**
     * Ищет кратчайший диапазон, содержащий указанный айпишник
     *
     * @param $ip
     * @return array
     */
    private function findShortedRange($ip):array
    {
        $min_range = [];
        foreach ($this->sorted_ranges as $range_rule) {
            if ($this->isIpInRange($ip, $range_rule)) {
                $min_range = $range_rule;
            }
        }
        return $min_range; // Если диапазон не найден
    }

    /**
     * Определяет наличие айпишника в диапазоне
     *
     * @param $ip
     * @param $range_rule
     * @return bool
     */
    private function isIpInRange($ip, $range_rule): bool
    {
        $range = $range_rule['range'];

        if ($ip === $range) {
            return true;
        }

        // Проверка формата CIDR
        if (str_contains($range, '/')) {
            list($subnet, $mask) = explode('/', $range);
            $subnetLong = $this->ipToLong($subnet);
            $ipLong = $this->ipToLong($ip);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // Проверка диапазона
        if (str_contains($range, '-')) {
            list($start, $end) = explode('-', $range);
            return $this->ipToLong($ip) >= $this->ipToLong($start) && $this->ipToLong($ip) <= $this->ipToLong($end);
        }

        // Проверяем, является ли диапазон wildcard
        if (str_contains($range, '*')) {
            $range = trim($range);
            $range_0 = str_replace('*', '0', $range);
            $range_255 = str_replace('*', '255', $range);
            return $this->ipToLong($ip) >= $this->ipToLong($range_0) && $this->ipToLong($ip) <= $this->ipToLong($range_255);
        }

        return false;
    }

    /**
     * Превращает signed int IP в беззнаковое число
     * (скорее всего не нужно для операций сравнения)
     *
     * @param $ip
     * @return string
     */
    private function ipToLong($ip): string
    {
        return sprintf('%u', ip2long($ip));
    }

    public function getIP(): ?string
    {
        if (PHP_SAPI === 'cli') {
            return '127.0.0.1';
        }

        if (!isset ($_SERVER['REMOTE_ADDR'])) {
            return null;
        }

        if (\array_key_exists("HTTP_X_FORWARDED_FOR", $_SERVER)) {
            $http_x_forwarded_for = \explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $client_ip = \trim(\end($http_x_forwarded_for));
            if (\filter_var($client_ip, FILTER_VALIDATE_IP)) {
                return $client_ip;
            }
        }

        return \filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : null;
    }

}