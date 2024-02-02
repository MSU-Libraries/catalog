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

function api_json(string $url) {
    $json = null;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpcode == 200) {
        $json = json_decode($response, true);
    }
    return [$httpcode, $json];
}

if ($_SERVER["REQUEST_URI"] === "/ping")  {
    echo "pong\n";
    exit;
}

$matches = [];
$r = preg_match(
    pattern: "/^\/record=([a-z0-9][0-9]*)/",
    subject: $_SERVER["REQUEST_URI"],
    matches: $matches
);

if ($r === 1) {
    $record = $matches[1] . compute_check_digit($matches[1]);
    list($httpcode, $json) = api_json(
        "http://" . getenv("STACK_NAME") . "-catalog_catalog/api/v1/search?lookfor=." .
        rawurlencode($record) . "&type=Bibnum&field[]=id"
    );

    $hrid = $json["records"][0]["id"] ?? null;
    $resultCount = $json["resultCount"] ?? null;
    if ($httpcode == 200 && !empty($hrid)) {
        header("Location: https://" . getenv("SITE_HOSTNAME") . "/Record/" . $hrid);
        exit(0);
    }
    elseif ($httpcode == 200 && $resultCount == 0) {
	http_response_code(404);
        echo "No record matching the requested bibnumber found in Catalog.";
    }
    else {
        http_response_code(503);
        echo "Service temporarily not available. Try again later.";
    }
}
else {
    http_response_code(400);
    echo "URL parameters passed did not meet expected format. Unable to redirect to Catalog.";
}
