# RandExp

RandExp will generate a random string that matches a given regex.

It is based and inspired by the JS library https://github.com/fent/randexp.js. 


## Installation

```shell
composer require tstache/randexp
```


## Usage

### Basic Usage

```php
include 'vendor/autoload.php';

$randExp = new \RandExp\RandExp('H[euioa]llo{1,10} (World|You)\n');
echo $randExp->generate();  // Output: Hellooooooo World
echo $randExp->generate();  // Output: Hullooooo You

$randExp = new \RandExp\RandExp('\d{4}');
echo $randExp->generate();  // Output: 2709
```

### More examples

```php
include 'vendor/autoload.php';

// Ignore-case option
$randExp = new \RandExp\RandExp('Hello', 'i');
echo $randExp->generate();  // Output: heLLO

// Capturing group reference
$randExp = new \RandExp\RandExp('<([a-z_]{1,5})>\d{4}</(\1)>');
echo $randExp->generate();  // Output: <nvd>5851</nvd>

// Maximum allowed repetition
$randExp = new \RandExp\RandExp('Hello*');
$randExp->maxRepetition = 2;
echo $randExp->generate();  // Output: Helloo

// Adding UTF-8 codepoints
$randExp = new \RandExp\RandExp('.{10}');
$randExp->charRange->add(127, 65535);
echo $randExp->generate(); // Output: Ꮻ啒窒녳ઠ椹䲀摷ꔞ
```


### Supported regex tokens

- `.`: Any character
- `^`: Position start
- `$`: Position end
- `\w`: Word
- `\W`: Not word
- `\d`: Int
- `\D`: Not int
- `\s`: Whitespace
- `\S`: Not whitespace
- `\b`: Word boundary
- `\B`: Non word boundary
- `\` followed by number: Capturing group reference
- `[` and `]`: Custom sets
- `(` and `)`: Capture group
- `|`: Choice
- `{` and `}`: Repetition range
- `?`: Repetition 0 or 1
- `+`: Repetition 1 or more
- `*`: Repetition 0 or more


## Tests

Run tests via:
```shell
vendor/bin/phpunit
```
