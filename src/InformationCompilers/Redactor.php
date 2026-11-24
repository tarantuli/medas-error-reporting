<?php

declare(strict_types=1);

namespace Medas\ErrorReporting\InformationCompilers;

/**
 * Keeps credentials out of what is logged or mailed about a request: the Authorization and
 * Cookie headers, with which whoever reads the log could act as that user, and values whose
 * name says they are secret (a password, a token) in the query and in a JSON or form body.
 * The value is replaced, so the report still shows it was there.
 */
final class Redactor
{
    public const string REMOVED = '[removed]';

    // $_SERVER names: the headers, also as Apache passes them on after each rewrite
    // (REDIRECT_HTTP_AUTHORIZATION), and what PHP takes from HTTP authentication.
    private const string SECRET_SERVER_NAMES = '/^(REDIRECT_)*HTTP_(AUTHORIZATION|PROXY_AUTHORIZATION|COOKIE)$|^PHP_AUTH_(PW|DIGEST)$/';

    // Field names: password, newPassword, current_password, token, refreshToken, clientSecret,
    // apiKey, api-key.
    private const string SECRET_FIELD_NAMES = '/pass(word|wd|phrase)|secret|token|api[-_]?key/i';

    public static function serverValue(string $name, mixed $value): mixed
    {
        return preg_match(self::SECRET_SERVER_NAMES, $name) === 1 ? self::REMOVED : $value;
    }

    /**
     * Query parameters or a decoded body, down every level.
     *
     * @param array<array-key, mixed> $fields
     * @return array<array-key, mixed>
     */
    public static function fields(array $fields): array
    {
        foreach ($fields as $name => $value) {
            if (is_string($name) && preg_match(self::SECRET_FIELD_NAMES, $name) === 1) {
                $fields[$name] = self::REMOVED;
            }
            elseif (is_array($value)) {
                $fields[$name] = self::fields($value);
            }
        }

        return $fields;
    }

    // A JSON or form body with its secret fields replaced; another body as it is.
    public static function body(string $body, string $contentType): string
    {
        $json = json_decode($body, true);

        if (is_array($json)) {
            return (string) json_encode(self::fields($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            parse_str($body, $form);

            return http_build_query(self::fields($form));
        }

        return $body;
    }
}
