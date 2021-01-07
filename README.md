## Настраиваем репозитории на Astra Linux Common Edition Орел

Подробная инструкция здесь: https://wiki.astralinux.ru/pages/viewpage.action?pageId=3276859

Кратко:

0. Перейти в sudo
```sudo -s```

1. Закоментить все в /etc/apt/sources.list

2. Добавить строку:

```deb [trusted=yes] https://download.astralinux.ru/astra/stable/orel/repository orel contrib main non-free```

3. подключить sury репо:

```wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg```

4. ```apt update```

## Установка Криптопро csp 5

1. распаковать архив /Cryptopro CSP 5/linux-amd64_deb.tgz
```tar -xvf linux-amd64_deb.tgz```

2. Запустить
```bash install.sh```

## Теперь самое веселое - Сборка php с поддержкой Cryptopro Cades

1. Установить все .deb пакеты из CADES Linux

```dpkg -i cprocsp-pki-cades_2.0.0-1_amd64.deb```

```dpkg -i lsb-cprocsp-devel_5.0.11535-4_all.deb```

```dpkg -i cprocsp-pki-plugin_2.0.0-1_amd64.deb```

```dpkg -i cprocsp-pki-phpcades_2.0.0-1_amd64.deb```

2. Перейти в папку с исходниками php той-же версии которая стоит в системе (В папке /php лежат исходники php7.2.23)

3. Выполнить
```./configure```

> Скорее всего будет ругаться что не хватает libxml2, установим

```apt-get install libxml2```

```apt-get install libxml2-dev```

4. Переходим в папку с исходниками phpcades Криптопро

```cd /opt/cprocsp/src/phpcades```

5. Копируем сюда патч php7_support.patch (он фиксит пути для поддержки php7, по умолчанию криптопрошная phpcades работает только с php5.6)

6. Выполнить

```patch -p0 < ./php7_support.patch```

7. В Makefile.unix указать путь до папки с исходниками (пример : PHPDIR=/home/tesonero/ovirug/install/php)

8. Выполнить

```eval `/opt/cprocsp/src/doxygen/CSP/../setenv.sh --64`; make -f Makefile.unix```

> Скорее всего будет ругаться на отсутствие libboost, поставим

```apt install libboost-all-dev```

После успешной компиляции должен появиться файл libphpcades.so

9. Находим папку с экстеншенами php

php-config --extension-dir
или
php -i | grep extension_dir

10. Копируем туда libphpcades.so или ставим симлинк

11. Активируем экстеншен
прописать libphpcades.ini в /etc/php/7.2/mods_available
прописать libphpcades.ini /etc/php/7.2/fpm/conf.d

```service php7.2-fpm restart```

> На заметку:
> Папка с исходниками должна называться /php иначе с большой вероятностью будут конфликты
> более правильный вариант установки php - apt install php , он сразу установит в систему php7.3
> Соответственно исходники надо будет искать для php7.3
> Т.к. сейчас в системе стоит 7.2 то исходники в /php лежат для него
> Не забудем поставить: apt install php7.2-dev php7.2-fpm

## Установка сертификатов в систему
Для примера в папке /install/keys лежит тестовый контейнер ключей и сертификаты полученные из него.

1. Нам надо установить сертификаты для пользователя www-data (от него запускажтся скипты в nginx и apache)

```su -s /bin/bash www-data```

2. Установим сертификаты для текущего пользователя (www-data)

```/opt/cprocsp/bin/amd64/certmgr -install -pfx -f /home/tesonero/ovirug/install/keys/new.pfx -pin 12345```

3. Выйдем из www-data

```exit```

4. Установим корневой сертификат в систему
скопировать в папку с корневыми сертификатами /usr/local/share/ca-certificates:
.../keys/new.cer(сертификат из контейнера ключей)
.../keys/test.cer(ТестовыйУЦООО-КРИПТО-ПРО)
.../keys/tsa.cer(сертификат службы времени)

Обновить сертификаты:

```update-ca-certificates```

*После установки сертификатов для пользователя www-data необходимо в файле конфигурации приложения config.ini указать хэш нужного сертификата
*Найти нужный сертификат с ключем PrivateKey Link : Yes можно командой ```/opt/cprocsp/bin/amd64/certmgr -list```
  
## Установка НОВОГО сертификата в систему

1. Установить в систему новый сертификат как указано в предыдущем пункте

2. Добавить в config.ini хэш нового сертификата с приватным ключем, например:
cert_sha1_hash = 501f1850f63fffb83d7d8c5c8020332fc83fbb6e

*узнать hash сертификата можно командой ```/opt/cprocsp/bin/amd64/certmgr -list```

> На заметку:
>  Просто установить сертификаты от рута не получится, на момент установки сертификата надо быть именно тем пользователем который будет сертификаты использовать.
> Получить сертификаты из контейнера ключей проще под Виндой используя утилиту КриптоПро CSP

сервис > Посмотреть сертификаты в контейнере > Обзор > Выбрать контейнер ключей > состав сертификата > копировать в файл

Это заклинание надо произнести два раза. Сначала ставим галочку "Да, экспортировать закрытый ключ", получим .pfx
Затем "Не экспортировать закрытый ключ", получим .cer (лучше BASE64 - так проще)

* Посмотреть список установленных сертификатов для текущего пользователя можно командой:

```/opt/cprocsp/bin/amd64/certmgr -list```

* Для цифровой подписи нужно использовать сертификат который имеет флаг
PrivateKey Link     : Yes

* Для того чтобы указать утелите КриптоПро использовать конкретный сертификат его можно идентифицировать через параметр
 CN или SHA1 Hash
 1. -CN "Тестовая организация"
 2. -thumbprint "501f1850f63fffb83d7d8c5c8020332fc83fbb6e"
 
 ===================================================================
 
 Документацию по методам php плагина можно найти здесь:
 
 https://cpdn.cryptopro.ru/ 
 
 КриптоПро ЭЦП. Руководство разработчика -> Справочник по ЭЦП SDK -> Интерфейс COM -> CAdESCOM: Интерфейсы
 
 
 В теории реализация методов CADES в PHP плагине должна соответствовать реализации CADES от Microsoft
 
 Для примера вот тут я искал названия полей для поиска сертификата: 
 
 https://docs.microsoft.com/en-us/windows/win32/seccrypto/certificates-find
 