![image](https://user-images.githubusercontent.com/58277138/164987861-8807085c-4db7-4eea-bad3-7deb03d73d68.png)

# Magento 2 Fire PHP Module

A simple module for sending debug data to the browser console.


**Installation**

```
composer require ho/magento2-debug-by-ho
```

and then enable the module

**How to use**

To use the module, a fire php browser plugin need to be installed
[FirePHP](https://chrome.google.com/webstore/detail/firephp-official/ikfbpappjhegehjflebknjbhdocbgkdi/related?hl=en)

```
hoLog(string $message, array $context);
```
Just use the method with message and data array and they will be logged into the browser console.

By default, the method will not work on production mode unless intentionally enabled from the admin panel.

**License**

MIT
