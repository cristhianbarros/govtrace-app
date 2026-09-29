<?php

namespace App\Infrastructure\Mail;

/**
 * With MAIL_MAILER=log an email is not delivered: it is written, whole, to
 * a log (storage/logs/mail.log). This reads the end of that file and gives
 * back the links of the latest mails, so a demonstration does not have to dig
 * them out of a log by hand. Laravel's log mailer already writes the body
 * decoded; only the subject stays an encoded word.
 */
final class SentLinks
{
    /** Where a link goes: a password to set or to recover. */
    private const LINK = '#https?://[^\s"\'<>\])]+/(?:set-password|reset-password)/[^\s"\'<>\])]+#';

    private const ENTRY_START = '/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] /m';

    /**
     * @return list<SentLink> the newest first
     */
    public static function fromFile(string $path, int $limit = 5, int $tailBytes = 4_194_304): array
    {
        if (! is_file($path)) {
            return [];
        }

        $links = [];
        foreach (array_reverse(self::entries(self::tail($path, $tailBytes))) as [$sentAt, $entry]) {
            $link = self::linkOf($sentAt, $entry);
            if ($link !== null) {
                $links[] = $link;
            }
            if (count($links) === $limit) {
                break;
            }
        }

        return $links;
    }

    /** The last $bytes of the file: a mail log can be hundreds of MB. */
    private static function tail(string $path, int $bytes): string
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - $bytes));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * @return list<array{0: string, 1: string}> [when, the entry], oldest first; a cut first entry is left out
     */
    private static function entries(string $content): array
    {
        $parts = preg_split(self::ENTRY_START, $content, flags: PREG_SPLIT_DELIM_CAPTURE);
        array_shift($parts); // whatever came before the first whole entry

        $entries = [];
        foreach (array_chunk($parts, 2) as [$sentAt, $entry]) {
            $entries[] = [$sentAt, $entry];
        }

        return $entries;
    }

    private static function linkOf(string $sentAt, string $entry): ?SentLink
    {
        $split = preg_split('/\R\R/', $entry, 2);
        if (count($split) < 2) {
            return null;
        }
        [$headers, $body] = $split;

        // "Subject" may be an encoded word (=?utf-8?Q?…?=), and a long one is folded.
        $headers = preg_replace('/\R[ \t]+/', ' ', $headers);
        $subject = preg_match('/^Subject: (.*)$/m', $headers, $found) ? trim(mb_decode_mimeheader($found[1])) : '';
        $to = preg_match('/^To: (.*)$/m', $headers, $found) ? trim($found[1]) : '';
        $to = preg_match('/<([^>]+)>/', $to, $email) ? $email[1] : $to;

        $text = html_entity_decode($body, ENT_QUOTES | ENT_HTML5);
        if (! preg_match(self::LINK, $text, $url)) {
            return null;
        }

        return new SentLink($to, $subject, $url[0], $sentAt);
    }
}
