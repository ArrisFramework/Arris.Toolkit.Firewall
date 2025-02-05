# Arris.Toolkit.Firewall

Library providing IP filtering features

| Type        | Syntax                      | Details                                                                                                                       |
|-------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| IPV4        | `192.168.0.1`               |                                                                                                                               |
| Range       | `192.168.0.0-192.168.1.60`  | Includes all IPs from `192.168.0.0` to `192.168.0.255`<br />and from `192.168.1.0` to `198.168.1.60`                          |
| Wild card   | `192.168.0.*`               | IPs starting with `192.168.0`<br />Same as IP Range `192.168.0.0-192.168.0.255`                                               |
| Subnet mask | `192.168.0.0/255.255.255.0` | IPs starting with `192.168.0`<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`                                      |
| CIDR Mask   | `192.168.0.0/24`            | IPs starting with `192.168.0`<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`<br />and `192.168.0.0/255.255.255.0` |

#### Basic usage

```php
use Arris\Toolkit\Firewall;

$whiteList = [
    '127.0.0.1',
    '192.168.0.*',
];

$blackList = [
    '192.168.0.50',
];

$firewall = new Firewall();

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


## License

Firewall is licensed under the [MIT license](LICENSE).

