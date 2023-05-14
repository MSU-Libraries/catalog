<?php
declare(strict_types = 1);

function compute_check_digit(string $id): string {
    $digits = array_values(
        array_map(
            array: array_filter(
                array: str_split($id),
                callback: fn(string $c): bool =>
                    preg_match(pattern: "/^[0-9]$/", subject: $c) === 1
            ),
            callback: fn(string $c): int => intval($c)
        )
    );

    $products = array_map(
        fn(int $digit, int $k): int =>
            $digit * ($k + 2),
        array_reverse($digits),
        array_keys($digits),
    );

    $remainder = array_sum($products) % 11;

    if ($remainder == 10) {
        return "x";
    } else {
        return strval($remainder);
    }
}

if ($_SERVER["REQUEST_URI"] === "/ping")  {
    echo "pong\n";
    exit;
}

$config = @parse_ini_file(__DIR__ . "/../app.ini");
if ($config === false) {
    throw new \Exception("Failed to parse INI file.");
}

$matches = [];
$r = preg_match(
    pattern: $config["pattern"],
    subject: $_SERVER["REQUEST_URI"],
    matches: $matches
);

if ($r === 1) {
    $record = $matches[1] . compute_check_digit($matches[1]);
    $target = str_replace(
        subject: $config["target"],
        search: '${record}',
        replace: $record
    );
    header("Location: " . $target);
} else {
    header("Location: " . $config["default_target"]);
}
