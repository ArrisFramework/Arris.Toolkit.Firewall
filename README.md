# Arris.Toolkit.Firewall

Library providing IP filtering features

| Type        | Syntax                      | Details                                                                                                                       |
|-------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| IPV4        | `192.168.0.1`               |                                                                                                                               |
| Range       | `192.168.0.0-192.168.1.60`  | Includes all IPs from `192.168.0.0` to `192.168.0.255`<br />and from `192.168.1.0` to `198.168.1.60`                          |
| Wild card   | `192.168.0.*`               | IPs starting with `192.168.0`<br />Same as IP Range `192.168.0.0-192.168.0.255`                                               |
| Subnet mask | `192.168.0.0/255.255.255.0` | (НЕ ПОДДЕРЖИВАЕТСЯ) IPs starting with `192.168.0`<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`                  |
| CIDR Mask   | `192.168.0.0/24`            | IPs starting with `192.168.0`<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`<br />and `192.168.0.0/255.255.255.0` |

## Basic usage

```php
use Arris\Toolkit\FireWall;

$whiteList = [
    '127.0.0.1',
    '192.168.0.*',
];

$blackList = [
    '192.168.0.50',
];

$firewall = new FireWall(
    defaultState: false,
    deferred_range_sorting: true 
);

$connAllowed = $firewall
    ->setDefaultState(false)
    ->addWhiteList($whiteList)
    ->addBlackList($blackList)
    ->validate('195.88.195.146')
    ->isAllowed()
;

if (!$connAllowed) {
    http_response_code(403); // Forbidden
    exit();
}
```
#### Constructor

```php
$firewall = new FireWall(
    defaultState: false,
    deferred_range_sorting: true 
);
```

- `defaultState` - rule for range `*.*.*.*`. Default **false**, i.e. **FORBIDDEN**. Allowed options:
  - `null` (equal to false)
  - `true|false`
  - `\Arris\Toolkit\FireWall\FireWallState::FORBIDDEN` или `Arris\Toolkit\FireWall\FireWallState::ALLOWED`
- `deferred_range_sorting` - use lazy range sorting:
  - `true`: at validate() call; use it if you test only one IP at once, most cases)
  - `false`: at addAnyList() call; use it if you test a lot if IPs with one ruleset

#### Add ranges

- `addWhiteList()` - add range to White list (Allowed), alias of `addAllowedList()` 
- `addBlackList()` - add range to Black List (Forbidden), alias of `addForbiddenList()`

Argument is string or array of strings

- `192.168.0.1` - alone IP
- `192.168.0.0-192.168.1.60` - range
- `192.168.0.*` - wildcard range (allowed range `*`, that is equal to `*.*.*.*`)
- `192.168.0.0/24` - CIDR range

Range `192.168.0.0/255.255.255.0` not supported now.

- `addRange(range, type)` - type is FireWallState enum value

## Сomplex and redundant example

```php
$firewall = new FireWall();
$i = $firewall
    ->addBlackList('192.168.0.0/16')            // 192.168.0.0 - 192.168.255.255
    ->addWhiteList('192.168.0.0/24')            // + 0-255
    ->addBlackList('192.168.0.10-192.168.0.80') // - 10-80
    ->addBlackList('192.168.0.100-192.168.0.121') // - 100-121
    ->addWhiteList('192.168.0.42')  // + 42
    ->addWhiteList('192.168.0.120') // + 120
    ->addBlackList('192.168.0.5')   // - 5

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
```

## Nuances

- when ranges overlap, the one with the lower power is "stronger"
- when ranges of equal power overlap, the result is not defined (most likely, the one that was declared earlier will be used)

## How does it work?

- We set IP ranges (white and black)
- Whether the full range (`*.*.*.*`) belongs to the black/white list depends on the settings
- Range powers are calculated
- Ranges are sorted by power
- The shortest range is found that includes the tested IP
- The type of this range is the state for the IP - ALLOWED or FORBIDDEN


## License

Firewall is licensed under the [MIT license](LICENSE).

