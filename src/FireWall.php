<?php

namespace Arris\Toolkit;

/**
 * Позволяет проверить, находится ли IP-адрес в диапазоне белого списка или черного
 *
 * Диапазоны передаются:
 * - "192.168.1.0/24" (CIDR)
 * - "192.168.1.1-192.168.1.255" (range)
 * - "192.168.1.1" (значение)
 *
 * Пример:
 * var_dump(
 *   (new Firewall())
 *        ->setWhiteList('127.0.0.1')
 *        ->validate()
 *        ->isAllowed()
 * );
 *
 */
class FireWall
{
    private array $white_list;
    private array $black_list;

    public bool $allowed = false;
    public bool $forbidden = true;
    public bool $default_state = false;

    /**
     * @var callable
     */
    private $callback;

    public function __construct()
    {
        $this->white_list = [];
        $this->black_list = [];
    }

    /**
     * Reset firewall state to default settings
     *
     * @return FireWall
     */
    public function reset():FireWall
    {
        $this->white_list = [];
        $this->black_list = [];
        $this->callback = null;
        $this->allowed = false;
        $this->forbidden = true;
        $this->default_state = false;
        return $this;
    }

    /**
     * Устанавливает состояние по-умолчанию (FORBIDDEN)
     * @param bool $allowed
     * @return $this
     */
    public function setDefaultState(bool $allowed = false):FireWall
    {
        $this->allowed = $allowed;
        $this->forbidden = !$allowed;
        $this->default_state = $allowed;
        return $this;
    }

    /*public function addRange($white = null, $black = null):FireWall
    {
        return $this;
    }*/

    /**
     * Дополняет белый список
     *
     * @param $list - массив строк или строка
     * @return $this
     */
    public function addWhiteList($list):FireWall
    {
        if (is_array($list)) {
            $this->white_list = array_merge($this->white_list, $list);
        }

        if (is_string($list)) {
            $this->white_list[] = $list;
        }

        return $this;
    }

    /**
     * Дополняет черный список
     *
     * @param $list - массив строк или строка
     * @return $this
     */
    public function addBlackList($list):FireWall
    {
        if (is_array($list)) {
            $this->black_list =\array_merge($this->black_list, $list);
        }

        if (is_string($list)) {
            $this->black_list[] = $list;
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function setHandler(callable $callback):FireWall
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Проверяет айпишник на вхождение в белый и черный списки
     * Если передан аргумент null - вызывается внутренняя функция getIP для
     * получения текущего IP.
     *
     * ВАЖНО: возвращает не результат проверки, а инстанс. Результат проверки
     * записывается в поля инстанса.
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

        $in_black = true;
        $in_white = false;

        foreach ($this->white_list as $block) {
            if ($this->isInRange($ip, $block)) {
                $in_white = true; // IP находится в белом списке
            }
        }

        foreach ($this->black_list as $block) {
            if ($this->isInRange($ip, $block)) {
                $in_black = false; // IP находится в черном списке
            }
        }


        $this->allowed = !$in_black || $in_white;
        $this->forbidden = $this->allowed;

        /*$this->allowed = $this->isInList($ip, $this->white_list);
        $this->forbidden = $this->isInList($ip, $this->black_list);*/

        return $this;
    }

    /**
     * Handle current request with callback handler
     * @TODO: тесты
     *
     * @param $ip
     * @return bool
     */
    public function handle($ip = null):bool
    {
        if (is_null($ip)) {
            $ip = $this->getIP();

            // всё еще может быть ошибка получения IP, значит он невалиден
            if (is_null($ip)) {
                return false;
            }
        }

        $this->validate($ip);

        $isAllowed = $this->check();

        return  is_null($this->callback)
            ? $isAllowed
            : call_user_func($this->callback, array($this, $isAllowed));
    }

    /**
     * Находится ли айпишник в списке разрешенных?
     * Отдает результат валидации
     *
     * @return bool
     */
    public function isAllowed():bool
    {
        // return !$this->forbidden && $this->allowed;
        return $this->allowed;
    }

    /**
     * Находится ли айпишник в списке запрещенных?
     * Отдает результат валидации
     *
     * @return bool
     */
    public function isForbidden():bool
    {
        // return !$this->isAllowed();
        return $this->forbidden;
    }

    /**
     * Где находится айпишник (не тестировано) - используется с HANDLE
     * @todo: ТЕСТЫ
     *
     * @return bool
     */
    public function check():bool
    {
        return $this->default_state ? (!$this->forbidden || $this->allowed) : ($this->allowed && !$this->forbidden);
    }

    /**
     * Находится ли IP хоть в одном из диапазонов в списке?
     *
     * @param string $ip
     * @param array $list
     * @return bool
     */
    private function isInList(string $ip, array $list):bool
    {
        $is = false;
        foreach ($list as $range) {
            $is = $is || $this->isInRange($ip, $range);
        }

        return $is;
    }

    /**
     * @param string $ip
     * @param string $range
     * @return bool
     */
    private function isInRange(string $ip, string $range):bool
    {
        $ip = trim($ip);

        // Проверяем, является ли диапазон диапазоном адресов
        if (str_contains($range, '-')) {
            list($start, $end) = explode('-', $range);
            return ip2long($ip) >= ip2long(trim($start)) && ip2long($ip) <= ip2long(trim($end));
        }

        // Проверяем, является ли диапазон wildcard
        if (str_contains($range, '*')) {
            $range = trim($range);
            $range_0 = str_replace('*', '0', $range);
            $range_255 = str_replace('*', '255', $range);
            return ip2long($ip) >= ip2long($range_0) && ip2long($ip) <= ip2long($range_255);
        }

        // Проверяем, является ли диапазон CIDR
        if (str_contains($range, '/')) {
            list($subnet, $mask) = explode('/', $range);
            $subnet = ip2long($subnet);
            $mask = ~((1 << (32 - (int)$mask)) - 1);
            return (ip2long($ip) & $mask) === ($subnet & $mask);
        }

        // Простой IP
        return \ip2long($ip) === \ip2long($range);
    }

    /**
     * Отдает IP
     *
     * @return string|null
     */
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