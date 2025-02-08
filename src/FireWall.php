<?php

namespace Arris\Toolkit;

use Arris\Toolkit\FireWall\FireWallState;

class FireWall
{
    private array $allowed_list;
    private array $forbidden_list;

    private array $united_list;

    public bool $allowed = false;
    public bool $forbidden = true;
    public bool $default_state = false;

    /**
     * Когда сортировать диапазоны айпишников:
     * TRUE - на этапе validate (сортирует диапазоны каждый раз при проверке, имеет смысл, если тестируем айпишник 1 раз)
     * FALSE - на этапе добавления (имеет смысл, если мы тестируем несколько айпишников по одному набору правил)
     * @var bool
     */
    private bool $deferred_range_sorting;

    /**
     * @param bool|FireWallState|null $defaultState
     * @param bool $deferred_range_sorting
     */
    public function __construct(FireWallState|bool $defaultState = null, bool $deferred_range_sorting = true)
    {
        $this->deferred_range_sorting = $deferred_range_sorting;
        $this->reset($defaultState);
    }

    /**
     * Резет правил (используется в тестах)
     *
     * @param FireWallState|bool|null $defaultState
     * @return FireWall
     */
    public function reset($defaultState = null):FireWall
    {
        $this->allowed_list = [];
        $this->forbidden_list = [];
        $this->united_list = [];

        $this->setDefaultState($defaultState);

        return $this;
    }

    /**
     * Состояние по-умолчанию для диапазона `*.*.*.*`
     *
     * @param FireWallState|bool|null $state
     * @return $this
     */
    public function setDefaultState($state):FireWall
    {
        if (is_null($state)) {
            $state = FireWallState::FORBIDDEN;
        }

        if (is_bool($state)) {
            $state = FireWallState::toEnum($state);
        }

        // if ($state instanceof FireWallState) { /* в остальном случае $state - инстанс FireWallState */}

        if ($state == FireWallState::ALLOWED) {
            $this->allowed = true;
            $this->addWhiteList('*.*.*.*');
            $this->default_state = true;
        } else {
            $this->allowed = false;
            $this->addBlackList('*.*.*.*');
            $this->default_state = false;
        }

        return $this;
    }

    /**
     * Добавляем диапазон в белый список
     *
     * @param $list
     * @return $this
     */
    public function addWhiteList($list):FireWall
    {
        if (is_array($list)) {
            foreach ($list as $l) {
                $this->addRange($l, FireWallState::ALLOWED);
            }
        }
        if (is_string($list)) {
            $this->addRange($list, FireWallState::ALLOWED);
        }

        return $this;
    }

    /**
     * Добавляем диапазон в черный список
     *
     * @param $list
     * @return $this
     */
    public function addBlackList($list):FireWall
    {
        if (is_array($list)) {
            foreach ($list as $l) {
                $this->addRange($l, FireWallState::FORBIDDEN);
            }
        }
        if (is_string($list)) {
            $this->addRange($list, FireWallState::FORBIDDEN);
        }

        return $this;
    }

    /**
     * Добавляем диапазон в конкретный список
     *
     * @param $range
     * @param FireWallState $type
     * @return $this
     */
    public function addRange($range, FireWallState $type):FireWall
    {
        if (is_array($range)) {
            foreach ($range as $r) {
                $this->addRange($r, $type);
            }
            return $this;
        }

        $this->united_list[] = [
            'range'     =>  $range,
            'type'      =>  $type,
            'capacity'  =>  $this->getRangeCapacity($range)
        ];

        if ($type == FireWallState::ALLOWED) {
            $this->allowed_list[] = $range;
        } else {
            $this->forbidden_list[] = $range;
        }

        if (!$this->deferred_range_sorting) {
            usort($this->united_list, function ($left, $right){
                return $right['capacity'] - $left['capacity'];
            });
        }

        return $this;
    }

    /**
     * Тестируем айпишник
     *
     * @param $ip
     * @return $this
     */
    public function validate($ip = null):FireWall
    {
        if (is_null($ip)) {
            $ip = $this->getIP();

            // всё еще может быть ошибка получения IP, значит он невалиден
            if (is_null($ip)) {
                return $this;
            }
        }

        // Если используется отложенная сортировка списков - сортируем их сейчас
        if ($this->deferred_range_sorting) {
            // $this->sortRanges();

            usort($this->united_list, function ($a, $b){
                return $b['capacity'] - $a['capacity'];
            });
        }

        // потом найти диапазон
        $found_range_definition = $this->findShortedRange($ip);

        if (empty($found_range_definition)) {
            // диапазон не найден, то есть айпишник регулируется правилом по-умолчанию для списка '*.*.*.*.'
            $this->allowed = $this->default_state;
        } else {
            $this->allowed = $found_range_definition['type'] === FireWallState::ALLOWED;
            $this->forbidden = !$this->allowed;
        }

        return $this;
    }

    /**
     * Проверяем, в белом списке ли тестирумый айпишник?
     *
     * @return bool
     */
    public function isAllowed():bool
    {
        return $this->allowed;
    }

    /**
     * Проверяем, в черном списке ли тестирумый айпишник?
     *
     * @return bool
     */
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
        /*foreach ($this->white_list as $range) {
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
        }*/

        usort($this->united_list, function ($a, $b){
            return $b['capacity'] - $a['capacity'];
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
        return match (substr_count($range, '*')) {
            4   =>  4_294_967_296,
            3   =>  16_777_216,
            2   =>  65_636,
            1   =>  256,
            // маски нет, одиночный айпишник
            default =>  1
        };
    }

    /**
     * Ищем кратчайший диапазон, содержащий айпишник
     *
     * @param $ip
     * @return array
     */
    private function findShortedRange($ip):array
    {
        $min_range = [];
        foreach ($this->united_list as $range_rule) {
            if ($this->isIpInRange($ip, $range_rule)) {
                $min_range = $range_rule;
            }
        }
        return $min_range; // Если диапазон не найден
    }

    /**
     * Определяем наличие айпишника в диапазоне
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
     * Превращаем signed int IP в беззнаковое число
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