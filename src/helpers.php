function envv(string $key, string $default = ''): string {
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}
