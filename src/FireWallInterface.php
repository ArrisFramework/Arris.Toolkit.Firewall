<?php

namespace Arris\Toolkit;

use Arris\Toolkit\FireWall\FireWallState;

interface FireWallInterface
{
    /**
     * Instantiate FireWall
     *
     * @param bool|FireWallState|null $defaultState
     * @param bool $deferred_range_sorting
     */
    public function __construct(FireWallState|bool $defaultState = null, bool $deferred_range_sorting = true);

    /**
     * Состояние по-умолчанию для диапазона `*.*.*.*`
     *
     * @param FireWallState|bool|null $state
     * @return $this
     */
    public function setDefaultState($state):FireWall;

    /**
     * Добавляем диапазон в белый список
     *
     * @param $list
     * @return $this
     */
    public function addWhiteList($list):FireWall;

    /**
     * Alias of addWhiteList()
     *
     * @param $list
     * @return $this
     */
    public function addAllowed($list):FireWall;

    /**
     * Добавляем диапазон в черный список
     *
     * @param $list
     * @return $this
     */
    public function addBlackList($list):FireWall;

    /**
     * Alias of addBlackList()
     *
     * @param $list
     * @return $this
     */
    public function addDenied($list):FireWall;

    /**
     * Добавляем диапазон в конкретный список
     *
     * @param $range
     * @param FireWallState $type
     * @return $this
     */
    public function addRange($range, FireWallState $type):FireWall;

    /**
     * Возвращает список правил, отсортированных по мощности
     *
     * @return array
     */
    public function getRanges():array;

    /**
     * Тестируем айпишник
     *
     * @param $ip
     * @return $this
     */
    public function validate($ip = null):FireWall;

    /**
     * Проверяем, в белом списке ли тестирумый айпишник?
     *
     * @return bool
     */
    public function isAllowed():bool;

    /**
     * Проверяем, в черном списке ли тестирумый айпишник?
     *
     * @return bool
     */
    public function isForbidden():bool;

    /**
     * Get IP. Empty if not found or not valid
     *
     * @return string
     */
    public function getIP(): string;

}