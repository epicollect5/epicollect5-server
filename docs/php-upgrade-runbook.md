# PHP Upgrade Runbook

Server-side PHP operations for Epicollect5: routine security patches, the standing
ImageMagick hold policy, and moving to a new PHP minor version.

**Scope** — Ubuntu 24.04, Apache with `mod_php`, PHP from the `ondrej/php` Launchpad PPA.

> **The web tier runs `mod_php`.** `php8.3-fpm` is not installed and must not be.
> `libapache2-mod-php8.3`, `libapache2-mod-php8.4` and `libapache2-mod-php8.5` all
> `Provide: libapache2-mod-php`, so **exactly one of them can be installed at a time**.
> Installing a second one removes the first.

## Contents

1. [Identify the box](#1-identify-the-box)
2. [Standing posture](#2-standing-posture)
3. [Procedure A — routine 8.3.x security patch](#3-procedure-a--routine-83x-security-patch)
4. [Procedure B — remove the ImageMagick wrappers](#4-procedure-b--remove-the-imagemagick-wrappers)
5. [Procedure C — move to PHP 8.4 / 8.5](#5-procedure-c--move-to-php-84--85)
6. [Verification checklist](#6-verification-checklist)
7. [Known constraints](#7-known-constraints)
8. [Package reference](#8-package-reference)

---

## 1. Identify the box

**Do not trust the hostname.** `deploy.php` declares all three environments as
`localhost()` on the same path, and a cloned droplet keeps the hostname it was
created with. A box named `epicollect5-staging` may well be serving production.

```bash
git -C /var/www/html_prod/current branch --show-current
grep -E '^APP_URL=' /var/www/html_prod/shared/.env
hostname   # informational only
```

| Branch | Environment |
|---|---|
| `master` | production |
| `staging` | staging |
| `dev` | dev |

## 2. Standing posture

### 2.1 Sources

PHP comes from the `ondrej/php` Launchpad PPA. `packages.sury.org` also publishes
8.3.x and may be present as a secondary source; if it is, the *same upstream version*
can carry a different version string depending on which repo served it:

```bash
grep -rn 'ondrej\|launchpad\|sury' /etc/apt/sources.list /etc/apt/sources.list.d/
```

Never run `apt autoremove` on these boxes — it drops `debsuryorg-archive-keyring`,
which signs the sury repository and cannot be re-fetched from the PPA.

### 2.2 Holds — two lists, opposite directions

```bash
apt-mark showhold
```

**Release the hold** on the core packages. A hold here means the next CVE never
reaches the web tier, silently:

```bash
sudo apt-mark unhold libapache2-mod-php8.3 php8.3 php8.3-cli php8.3-common \
  php8.3-opcache php8.3-readline php8.3-bcmath php8.3-curl php8.3-dev \
  php8.3-gd php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip
```

**Keep the hold** on the ImageMagick wrappers. These are the only routes into
PHP 8.5 (see [§8](#8-package-reference)):

```bash
for p in php-imagick php-imagick-all-dev php8.4-imagick php8.3-imagick; do
  dpkg-query -W -f='${db:Status-Abbrev}' "$p" 2>/dev/null | grep -q '^ii' && sudo apt-mark hold "$p"
done
```

The loop skips packages that are not installed. `apt-mark hold` pins a package
against upgrade *and* removal, which is what breaks the dependency chain before it
can fire.

### 2.3 Standing regression check

Run after **any** `apt upgrade`, unattended or manual:

```bash
dpkg-query -W -f='${Package} ${Version}\n' 'php8.5*' libapache2-mod-php8.5 2>&1 | sort
```

Must print **nothing**. If it prints anything, PHP 8.5 has been installed.

---

## 3. Procedure A — routine 8.3.x security patch

Use for a new `8.3.y` release. Expected duration: 20–40 minutes, most of it waiting
on the PPA CDN.

### Phase 0 — Pre-flight

**0.1 Identify the box** (§1) and **take a DigitalOcean snapshot**. sury prunes old
builds, so the current version cannot be reinstalled from the repository. The
snapshot is the only rollback.

**0.2 Capture the installed set, with install status:**

```bash
dpkg-query -W -f='${Package} ${Version} ${db:Status-Abbrev}\n' \
  'php8.3*' libapache2-mod-php8.3 | sort -k1 | tee /tmp/php83-before.txt
```

`ii` = installed, `un` = known to dpkg but not installed. The `un` rows are virtual
packages *Provided* by `php8.3-common` / `-xml` / `-mysql`, plus `php8.3-fpm` and
`php8.3-cgi` which are genuinely not installed — that is expected and correct.

**0.3 Pin check** — no extension may pin `php8.3-common` to an exact version:

```bash
for p in $(dpkg-query -W -f='${Package}\n' 'php8.3*' | sort -u); do
  pin=$(dpkg-query -W -f='${Depends}\n' "$p" 2>/dev/null | tr ',' '\n' | grep -i 'php8\.3-common')
  [ -n "$pin" ] && echo "$p -> $pin"
done
```

The only acceptable output is `php8.3-imagick -> php8.3-common` (unversioned). Any
`= 8.3.17-...` pin stops the run.

**0.4 Holds** — apply §2.2, then confirm:

```bash
apt-mark showhold
```

### Phase 1 — Refresh the index

```bash
for i in $(seq 1 10); do
  sudo apt-get update -o Acquire::Retries=5 -o Acquire::http::Timeout=30 && break
  sleep 15
done
```

`W: Target ... configured multiple times` warnings come from duplicated sources in
the image and are harmless.

> **Gate 1 — the candidate must be the version you intend to install.**
>
> ```bash
> apt-cache policy libapache2-mod-php8.3 php8.3-common \
>   | grep -oP 'Candidate: \K[0-9.]+' | sort -u
> ```
>
> Must print exactly **one** line, the target version. Two lines means the two
> packages disagree. A stale version means an index fetch 503'd — `apt-get update`
> keeps stale lists silently on failure — so re-run Phase 1.

Assert the *upstream* version, not the full version string: the suffix
(`+ubuntu24.04.1+deb.sury.org+1` vs `+0~…gbp…`) depends on which repository served
the package.

### Phase 2 — Upgrade

```bash
for i in $(seq 1 30); do
  sudo NEEDRESTART_MODE=l DEBIAN_FRONTEND=noninteractive apt-get \
    -o Acquire::Retries=5 -o Acquire::http::Timeout=30 \
    install -y --only-upgrade \
    -o Dpkg::Options::="--force-confold" \
    libapache2-mod-php8.3 php8.3 php8.3-cli php8.3-common php8.3-opcache \
    php8.3-readline php8.3-bcmath php8.3-curl php8.3-dev php8.3-gd \
    php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip \
  && { echo "=== SUCCESS ==="; break; }
  sleep 20
done
```

| Flag | Why |
|---|---|
| `--only-upgrade` | Never install anything new, so `php8.3-fpm` / `php8.3-cgi` stay absent. |
| `--force-confold` | Keep the tuned `php.ini` and `conf.d` instead of letting dpkg replace them. |
| `NEEDRESTART_MODE=l` | Stop `needrestart` bouncing Apache mid-transaction. |
| `DEBIAN_FRONTEND=noninteractive` | No prompts. |
| retry loop | The PPA CDN returns intermittent 503s. Each failed pass banks its downloads in `/var/cache/apt/archives/`, so the loop converges. |

**No ImageMagick packages in the list.** See [§8](#8-package-reference).

`=== SUCCESS ===` is the only signal that matters. A partial transaction leaves dpkg
mid-configure; finish it with `sudo dpkg --configure -a` before continuing.

### Phase 3 — Verify the packages

> **Gate 2 — exactly one version across the upgraded set.**
>
> ```bash
> dpkg-query -W -f='${Package} ${Version}\n' \
>   libapache2-mod-php8.3 php8.3 php8.3-cli php8.3-common php8.3-opcache \
>   php8.3-readline php8.3-bcmath php8.3-curl php8.3-dev php8.3-gd \
>   php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip \
>   | awk '{print $2}' | sort -u
> ```
>
> Must print exactly **one** line. Two or more is a split state — stop.

Do not use `dpkg-query -W 'php8.3*'` as the gate. That glob also matches PECL
extensions (`php8.3-imagick`, `php8.3-http`, `php8.3-raphf`) which carry their own
independent version numbers and will always produce extra lines.

### Phase 4 — Verify the runtime

```bash
php -v
readlink -f "$(which php)"
php -m | grep -iE 'imagick|simdjson|posix|openssl|mbstring'
```

Expect the target version, `/usr/bin/php8.3`, and all extensions present.

```bash
sudo apache2ctl configtest
systemctl status apache2 --no-pager | head -8
```

**Do not restart Apache manually.** `libapache2-mod-php8.3`'s postinst calls
`apache2_reload restart`, so the package restarts Apache itself. Confirm it happened
by the start time in `systemctl status` — it must be newer than the `=== SUCCESS ===`
moment. A manual restart only adds downtime.

If Apache is `failed` or `activating`:

```bash
sudo journalctl -u apache2 -n 30 --no-pager
```

### Phase 5 — Confirm the web tier

`php -v` shows the CLI. To see what mod_php actually loaded, probe the docroot:

```bash
DOCROOT=$(grep -hoP 'DocumentRoot\s+"?\K[^"\s]+' /etc/apache2/sites-enabled/* | head -1)
echo '<?php echo PHP_VERSION." | ".php_sapi_name()."\n";' > "$DOCROOT/__phpver.php"
curl -s "https://<host>/__phpver.php"
rm -f "$DOCROOT/__phpver.php"
```

Expect the target version and `apache2handler`.

Then the smoke test — **one entry upload and one media download**. This exercises
Imagick under mod_php, the S3/Spaces driver and the SAPI together, and is the only
check that catches a broken extension.

### Phase 6 — Hygiene

```bash
apt-mark showhold                                  # core must NOT be held
dpkg-query -W -f='${Package} ${Version}\n' 'php8.5*' 2>&1 | sort   # must be empty
dpkg-query -W -f='${Package} ${Version}\n' 'php8.3*' libapache2-mod-php8.3 | sort -k1 \
  > /tmp/php83-after.txt
diff /tmp/php83-before.txt /tmp/php83-after.txt
```

---

## 4. Procedure B — remove the ImageMagick wrappers

**Required before any minor PHP move.** PHP 8.3 loses security support on
**31 December 2026**, so a move to 8.4 or 8.5 is unavoidable. The ImageMagick
packaging is what makes that move dangerous.

The `php-imagick` wrapper package `Depends: php8.5-imagick` — that is its only
dependency. Every route into PHP 8.5 runs through it:

| Package | Version | Dependency |
|---|---|---|
| `php-imagick` | 3.8.1 | `php8.5-imagick` |
| `php-imagick-all-dev` | 3.8.1 | every `phpX.Y-imagick`, 5.6 → 8.5 |
| `php8.3-imagick` | 3.8.1 | `Breaks: php-imagick (<< 3.8.1~)` |
| `php8.4-imagick` | 3.8.1 | `Breaks: php-imagick (<< 3.8.1~)` |

Upgrading `php8.3-imagick` to 3.8.1 *Breaks* the wrapper at any version below
3.8.1, so apt must raise or remove `php-imagick` — and raising it lands on
`php8.5-imagick`. Nothing requests PHP 8.5 explicitly; the packaging drags it in.

Holding the packages in §2.2 prevents this, but a hold is a policy, not a structure.
Removing the wrappers makes 8.5 unreachable by dependency.

### Steps

**B.1 Record what is installed:**

```bash
dpkg-query -W -f='${Package} ${Version} ${db:Status-Abbrev}\n' \
  php-imagick php-imagick-all-dev php8.3-imagick php8.4-imagick php8.5-imagick 2>&1
```

**B.2 Remove the wrappers.** `php8.3-imagick` *Provides* `php-imagick`, so removing
the wrapper does not remove the extension:

```bash
sudo apt-mark unhold php-imagick php-imagick-all-dev php8.4-imagick
sudo apt-get purge php-imagick php-imagick-all-dev php8.4-imagick
```

`php-imagick-all-dev` is development headers for compiling extensions — nothing in
the app needs it.

**B.3 Verify the extension still loads:**

```bash
php -m | grep -i imagick
```

**B.4 From now on `php8.3-imagick` can move freely**, because there is no wrapper
left to satisfy the `Breaks`:

```bash
sudo apt-get install --only-upgrade php8.3-imagick
```

Upgrading to 3.8.1 is optional: its release notes contain **no security fixes**
(only *"Fixed compiling against PHP 8.5"* and *"Fixed getImage(s)Blob return
type"*). Staying on 3.8.0 costs nothing and avoids the churn.

**B.5 Confirm 8.5 is unreachable:**

```bash
apt-get install --dry-run php8.3-imagick 2>&1 | grep -i 'php8\.5'   # must print nothing
```

---

## 5. Procedure C — move to PHP 8.4 / 8.5

Rehearse on staging. This is a different operation from Procedure A and carries
risks that a patch upgrade does not.

### C.1 Preconditions

- [ ] Procedure B complete — the ImageMagick wrappers are gone. **This is not
      optional for a version move.** `php-imagick-all-dev` 3.8.1 depends on imagick
      for PHP 5.6, 7.0–7.4 and 8.0–8.5, so if apt is allowed to upgrade it during
      the move it drags **seven PHP versions'** imagick packages onto the box.
- [ ] Snapshot taken.
- [ ] §7 constraints read: **`league/oauth2-server` 9.3.0 caps the app at PHP 8.5.**
      PHP 8.6 is not supported by the current dependency set.
- [ ] Application tested on the target version in staging, including the two
      implicit-nullable deprecations listed in §7.

### C.1a Capture the current configuration first

Every PHP minor version gets a **fresh** `php.ini` seeded from stock defaults, and
stock `upload_max_filesize` is 2M — which would silently break every media upload
and look like an application bug. Capture the 8.3 configuration before touching
anything:

```bash
sudo cp -a /etc/php/8.3/apache2/php.ini /root/php83-apache2-php.ini.bak
sudo cp -a /etc/php/8.3/apache2/conf.d  /root/php83-apache2-conf.d.bak
php -i | grep -E '^(memory_limit|upload_max_filesize|post_max_size|max_execution_time|max_input_vars|date.timezone|opcache\.)' | sort
```

Keep the output. It is the checklist for C.4.

### C.2 The package set

For **8.4** (currently `8.4.27` in the PPA):

```
libapache2-mod-php8.4 php8.4 php8.4-cli php8.4-common php8.4-opcache \
php8.4-readline php8.4-bcmath php8.4-curl php8.4-dev php8.4-gd \
php8.4-mbstring php8.4-mysql php8.4-xml php8.4-zip php8.4-imagick
```

For **8.5** (currently `8.5.12` in the PPA) — **identical except that there is no
`php8.5-opcache` package**, because in the 8.5 series OPcache is compiled into the
PHP core binary rather than shipped as a shared module:

```
libapache2-mod-php8.5  Depends: media-types | mime-support, php8.5-cli, php8.5-common (= 8.5.12-…), tzdata, ucf, apache2-api-20120211, apache2-bin, …
```

> ### OPcache in PHP 8.5 — read this before copying any ini
>
> There is **no `php8.5-opcache` package**, and that is expected rather than a gap.
> Verified directly against the built artefacts:
>
> | Evidence | PHP 8.3 | PHP 8.5 |
> |---|---|---|
> | `Zend OPcache` string in the CLI binary | **0 occurrences** | **1 occurrence** |
> | `opcache.so` in the extension dir | `php8.3-opcache` package | **none — built into the binary** |
> | `opcache.enable`, `opcache.jit`, `opcache.memory_consumption`, `opcache.max_accelerated_files`, `opcache.revalidate_freq`, `opcache.validate_timestamps` registered as ini entries | via the shared module | **present in the binary** |
>
> So PHP 8.5 **does** have OPcache: it is statically linked, it is enabled by default
> (`opcache.enable` defaults to `1`), and every `opcache.*` ini directive still
> works. `opcache_reset()` still exists, so `SystemClearOpcache` and
> `after_pull-prod.sh` behave normally.
>
> **The one thing that breaks:** do **not** copy an 8.3/8.4 OPcache ini into 8.5.
> Those contain `zend_extension=opcache.so`, and 8.5 has no `opcache.so` to load —
> PHP will log a failure to load it on every request. 8.5 needs no ini for OPcache
> at all. For **8.4** the opposite applies: `php8.4-opcache` ships a real
> `opcache.so` and its postinst creates the `zend_extension` ini via `php_invoke
> enmod`, so let the package do it and do not hand-copy one.
>
> Check what a version actually has, rather than assuming:
>
> ```bash
> php -m | grep -i opcache
> php -i | grep -E 'opcache\.enable|zend_extension'
> ```

```
libapache2-mod-php8.5 php8.5 php8.5-cli php8.5-common \
php8.5-readline php8.5-bcmath php8.5-curl php8.5-dev php8.5-gd \
php8.5-mbstring php8.5-mysql php8.5-xml php8.5-zip php8.5-imagick
```

Listing a non-existent package makes apt fail the whole transaction.

### C.3 The mod_php slot switches over

`libapache2-mod-php8.4` `Provides: libapache2-mod-php`, the same virtual package
`libapache2-mod-php8.3` provides. apt therefore **removes the 8.3 module** as part
of installing the 8.4 one. That is expected. It also means the two cannot coexist,
so there is no window in which Apache can load either — plan a brief outage.

The `php8.3-*` runtime packages themselves are *not* removed, so `php8.3` stays
available as a CLI and the rollback in C.9 stays cheap.

### C.4 `php.ini` is NOT carried over

**This is the step most likely to be missed.**

Each PHP minor version has its own configuration tree:

```
/etc/php/8.3/{cli,apache2,fpm}/php.ini
/etc/php/8.3/{cli,apache2,fpm}/conf.d/
```

On install, `php8.4-cli`'s postinst seeds `/etc/php/8.4/cli/php.ini` from the stock
`php.ini-production.cli` via `ucf`, and the Apache SAPI gets its own
`/etc/php/8.4/apache2/php.ini`. **The 8.3 tuning is not copied.** The 8.4 files start
from stock defaults.

Diff the two to find what needs re-applying:

```bash
diff <(grep -vE '^\s*;|^\s*$' /etc/php/8.3/apache2/php.ini) \
     <(grep -vE '^\s*;|^\s*$' /etc/php/8.4/apache2/php.ini)
```

Review the diff and apply every app-relevant directive to the 8.4 file — typically
`memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`,
`date.timezone`, and the OPcache block.

Custom `conf.d` drop-ins are per-version too and are **not** copied either:

```bash
ls -la /etc/php/8.3/apache2/conf.d/
# copy any file that is not shipped by a Debian package across to 8.4
```

```bash
dpkg -S /etc/php/8.3/apache2/conf.d/*.ini 2>/dev/null   # files with no owner are yours
```

### C.5 `update-alternatives` moves `/usr/bin/php`

Each `phpX.Y-cli` registers itself with `update-alternatives` at priority `8Y`:

```
update-alternatives --install /usr/bin/php php /usr/bin/php8.3 83 ...
```

Higher priority wins, so installing `php8.4-cli` flips `/usr/bin/php` to 8.4, and
installing `php8.5-cli` flips it to 8.5. `phar` and `phar.phar` move with it.

```bash
readlink -f "$(which php)"    # /usr/bin/php8.4
update-alternatives --display php
```

This is expected. It is also why `/usr/bin/php` must never be pointed at 8.5 while
the app still runs on 8.3 — a mixed CLI and SAPI is the classic way an upgrade goes
wrong.

### C.6 Extensions

Install the 8.4/8.5 equivalent of every extension the app loads. The app requires
`ext-posix`, `ext-openssl`, `ext-mbstring`, `ext-simdjson` (all in `composer.json`)
and uses Imagick via `Intervention Image` in `app/Services/Media/PhotoSaverService.php`,
`app/Services/Media/FileMoverService.php` and `app/Services/Project/ProjectAvatarService.php`.

> **`ext-simdjson` is not in the PPA and not in Ubuntu noble universe.** It is a hard
> requirement in `composer.json` but ships as a separate package from elsewhere —
> on the servers it is installed with `pecl install simdjson`, and on a Homebrew
> PHP it lands in `/opt/homebrew/lib/php/pecl/<api-version>/`.
>
> **A PECL extension is compiled against one PHP API version and must be rebuilt for
> the target one.** The extension directory is version-stamped — `20230831` for 8.3,
> `20240924` for 8.4, `20250925` for 8.5 — so the 8.3 `simdjson.so` simply will not
> load on 8.4 and the extension goes missing, taking `simdjson_decode()` with it.
>
> Before moving versions, find where the running box gets it from:
>
> ```bash
> php -r 'echo ini_get("extension_dir"), "/simdjson.so", PHP_EOL;'
> dpkg -S "$(php -r 'echo ini_get("extension_dir"), "/simdjson.so";')"
> ```
>
> The first line gives the version-stamped path; the second identifies the owning
> package. Check that the same source publishes a build for the target PHP version.
>
> simdjson 4.0.0 (October 2022, the latest release on PECL) has been verified to
> **compile cleanly against PHP 8.4's headers**, so the extension is not a blocker.
> Rebuild it after the version switch, with the target's `phpize`/`pecl`:
>
> ```bash
> phpize && ./configure --with-php-config=/usr/bin/php-config8.4 && make && make install
> echo "extension=simdjson.so" > /etc/php/8.4/mods-available/simdjson.ini
> php_invoke enmod 8.4 apache2 simdjson
> ```
>
> It is also not optional at runtime: the app calls `simdjson_decode()` directly in
> `app/Services/Mapping/DataMappingService.php:159` and `:303` (entry mapping) and
> `app/Http/Controllers/Api/Entries/View/ViewEntriesLocationsController.php:72`
> (geo locations). A missing extension is a fatal error on those paths, not a
> degraded feature.

### C.7 Composer

`composer.json` requires `php >=8.3` and `composer.lock` contains **no
`platform-overrides`**, so neither file needs editing for a version bump. Re-run the
install so the autoloader is generated against the new platform, then check the
requirements:

```bash
cd /var/www/html_prod/current
composer install --no-interaction
composer check-platform-reqs
composer dump-autoload -o
```

`check-platform-reqs` is the authoritative confirmation that every `ext-*` in
`composer.json` is present. Note it also checks `php` against `>=8.3`, which the new
version satisfies.

### C.8 Verify

Repeat [§3 Phase 3](#phase-3--verify-the-packages) through
[§3 Phase 5](#phase-5--confirm-the-web-tier) with the 8.4/8.5 package names, then:

```bash
php -v
php -m | grep -iE 'imagick|simdjson|posix|openssl|mbstring'
sudo apache2ctl configtest
systemctl status apache2 --no-pager | head -5
```

The smoke test is mandatory: upload an entry, then download a media file.

### C.9 Rollback

```bash
sudo apt-get install --only-upgrade libapache2-mod-php8.3 php8.3-cli php8.3-common
```

Because the mod_php slot is exclusive, this removes `libapache2-mod-php8.4` and
restores 8.3. If the 8.4 configuration is what broke, restore `/etc/php/8.3/` from
the snapshot.

---

## 6. Verification checklist

Run after every PHP change.

| # | Check | Expected |
|---|---|---|
| 1 | `apt-cache policy libapache2-mod-php8.X php8.X-common \| grep -oP 'Candidate: \K[0-9.]+' \| sort -u` | one version |
| 2 | scoped `dpkg-query` over the upgraded set | one version line |
| 3 | `dpkg-query -W -f='${Package} ${Version}\n' 'php8.5*'` | empty |
| 4 | `php -v` | target version |
| 5 | `readlink -f "$(which php)"` | `/usr/bin/php8.X` |
| 6 | `php -m` | imagick, simdjson, posix, openssl, mbstring |
| 7 | `sudo apache2ctl configtest` | `Syntax OK` |
| 8 | `systemctl status apache2` | `active (running)` |
| 9 | docroot probe | target version, `apache2handler` |
| 10 | entry upload + media download | works |
| 11 | `apt-mark showhold` | wrappers held, core not held |
| 12 | `composer check-platform-reqs` | no failures |

---

## 7. Known constraints

**`league/oauth2-server` 9.3.0 caps the app at PHP 8.5.** Its constraint is
`~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0` — PHP 8.6 is **not** supported.
Any move past 8.5 requires a `league/oauth2-server` release that widens the
constraint, and a matching `laravel/passport` release.

**Dependency constraints on the target version** (from `composer.lock`):

| Package | Version | Requires PHP |
|---|---|---|
| `laravel/framework` | v13.15.0 | `^8.3` |
| `laravel/passport` | v13.7.5 | `^8.2` |
| `league/oauth2-server` | 9.3.0 | `~8.1.0 … ~8.5.0` |
| `intervention/image` | 3.11.8 | `^8.1` |
| `symfony/http-foundation` | v7.4.13 | `>=8.2` |

Verified identical on every deployed branch — `master`, `origin/staging`, `dev` and
`feature/import-api`. The differences between them are unrelated to PHP:
`feature/import-api` adds `opis/json-schema` (`^7.4 || ^8.0`) and bumps
`guzzlehttp/guzzle` to 7.13.1 (`^7.2.5 || ^8.0`), neither of which has an upper
bound. The `ext-*` requirement set is also identical on all four.

**No `config.platform` in `composer.json` and no `platform-overrides` in
`composer.lock`**, so no dependency file changes are needed for a version bump.

**Two implicitly nullable parameters** emit `E_DEPRECATED` on PHP 8.4+:

| File | Line | Signature |
|---|---|---|
| `app/Libraries/Auth/Jwt/JwtGuard.php` | 72 | `__construct(UserProvider $provider, Request $request = null, Jwt $jwt)` |
| `app/Http/Validation/Entries/Upload/RuleAnswers.php` | 209 | `validateAnswer(…, EntryStructureDTO $entryStructure = null): mixed` |

Fix by making the null explicit (`?Request $request = null`). `mixed` parameters are
**not** affected — `mixed` already includes null, so
`app/DTO/ProjectDTO.php:202` and `:264` are fine.

Both real sites are identical on `master`, `origin/staging`, `dev` and
`feature/import-api`, at the same line numbers.

**PHP 8.3 security support ends 31 December 2026.** After that date no 8.3.x
release will carry security fixes, so the move to 8.4 or 8.5 must happen before it.

---

## 8. Package reference

### The route into PHP 8.5

```
php-imagick            3.8.1   Depends: php8.5-imagick
php-imagick-all-dev    3.8.1   Depends: php8.5-imagick, php8.4-imagick, … , php5.6-imagick
php8.3-imagick         3.8.1   Breaks: php-imagick (<< 3.8.1~)   Depends: php8.3-common, phpapi-20230831
php8.4-imagick         3.8.1   Breaks: php-imagick (<< 3.8.1~)   Depends: php8.4-common, phpapi-20240924
php8.5-imagick         3.8.1   Breaks: php-imagick (<< 3.8.1~)   Depends: php8.5-common, phpapi-20250925
```

`php8.3-imagick` 3.8.0 depends on `php8.3-common` **unversioned**, and 8.3.36
`Provides: phpapi-20230831` — the same API level as 8.3.17. The Zend module API
number is frozen within an 8.3.x series, so 3.8.0 stays ABI-compatible across the
whole series and needs no upgrade to receive the 8.3.36 security fixes.

### The mod_php slot

```
libapache2-mod-php8.3  8.3.36  Provides: libapache2-mod-php, php-json, php8.3-json, phpapi-20230831
libapache2-mod-php8.4  8.4.27  Provides: libapache2-mod-php, php-json, php8.4-json, phpapi-20240924
libapache2-mod-php8.5  8.5.12  Provides: libapache2-mod-php, php-json, php8.5-json, phpapi-20250925
```

All three `Provide: libapache2-mod-php` — mutually exclusive, one at a time.

Each also depends on its own common package at an **exact** version
(`php8.X-common (= <version>)`), unlike `php8.3-imagick`, whose `php8.3-common`
dependency carries no version constraint.

### The metapackage

```
php8.3  8.3.36  Depends: libapache2-mod-php8.3 | php8.3-fpm | php8.3-cgi, php8.3-common
php8.4  8.4.27  Depends: libapache2-mod-php8.4 | php8.4-fpm | php8.4-cgi, php8.4-common
php8.5  8.5.12  Depends: libapache2-mod-php8.5 | php8.5-fpm | php8.5-cgi, php8.5-common
```

The dependency is **unversioned**, so the metapackage drifts freely and its version
says nothing about the binaries. `php8.3` sitting at a different version than
`php8.3-common` is normal, not a symptom of a partial upgrade. The `|` alternative
means the installed `libapache2-mod-php8.X` satisfies it — `php8.X-fpm` is never
pulled in.
