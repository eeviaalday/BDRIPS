<?php

namespace App\Services;

use App\Models\User;
use voku\helper\ASCII;

/**
 * The password a resident's digital copy is encrypted with.
 *
 * THE RULE, IN ONE PLACE
 *
 * mm/dd/yy of the resident's birthdate, then the first letter of their surname,
 * uppercased. `03/17/94` of a Cruz is `03/17/94C`.
 *
 * It is derived rather than chosen so that nobody has to invent, remember, reset
 * or write down a second password, and so that the resident who needs a reprint
 * next month opens the same file without ringing the office to ask what they were
 * given. The two inputs are things the resident already knows without looking
 * anything up, which is what makes it usable at a hall counter where the person
 * holding the phone is not always the person named on the certificate.
 *
 * WHY IT IS SAFE ENOUGH, STATED HONESTLY
 *
 * A birthdate and an initial is not a strong secret. It is a barrier that stops a
 * casual reader - a shared computer, a forwarded email, a lost phone - from
 * opening the file, and it is not protection against anybody who already knows
 * the resident. It is also why the PDF is built with the strongest encryption
 * the library offers and why nothing else about the resident is in it: a document
 * that can only be opened with the resident's own details, issued over an
 * authenticated session to that resident and to nobody else.
 *
 * The consequence worth stating plainly is that this is not a substitute for the
 * office's own judgement. The footer printed on every copy says so in as many
 * words, and the setting is editable for exactly that reason.
 *
 * WHY ACCENTS ARE STRIPPED BEFORE THE INITIAL IS TAKEN
 *
 * The initial is compared and typed by hand, so it has to be a letter somebody can
 * actually enter on any keyboard. `Ñoño` is `N`, `Çruz` is `C`, and a resident whose
 * surname begins with a character that has no Latin equivalent gets a password
 * they cannot guess at unless we tell them the mapping - so instead the rule
 * refuses to produce a password at all in that case, and the resident is sent to
 * the hall, rather than receiving something built on a guess.
 *
 * WHY THE SURNAME IS THE LAST WORD AND NOT THE FIRST
 *
 * Philippine names are written family name first: a resident recorded as
 * "Juan Dela Cruz Santos" has the surname Santos. Taking the first word would give
 * J for every Juan in the barangay and a guessable password for all of them, so
 * the last whitespace-delimited token is taken. That is the same convention the
 * resident's own screens use when they read a name back.
 *
 * THE PASSWORD IS NEVER PERSISTED
 *
 * Not on the request, not on the delivery, not in the audit context and not in the
 * response. It exists in memory for the length of one request and then goes, which
 * is why the only copy of it is the one the resident was handed.
 */
final class DigitalCopyPassword
{
    /**
     * The password for one resident, or null when it cannot be derived.
     *
     * Null is a real answer and the caller must handle it: a resident with no
     * birthdate on file, or whose surname has no letter we can put on a keyboard,
     * gets no file rather than a file opened by a guess. The caller turns that into
     * an instruction to ask at the hall, which is the honest outcome - a document
     * that cannot be encrypted to its owner's own details should not be emailed to
     * anybody.
     */
    public static function for(?User $user): ?string
    {
        $birthDate = $user?->date_of_birth;

        if ($birthDate === null) {
            return null;
        }

        $initial = self::surnameInitial($user->name);

        if ($initial === null) {
            return null;
        }

        // 'y' rather than 'Y': a two-digit year is what makes the password
        // typeable, and the century does not need disambiguating because the
        // resident is the only person this file is ever offered to.
        return $birthDate->format('m/d/y').$initial;
    }

    /**
     * The first ASCII letter of the surname, uppercased.
     *
     * Null when there is no surname to read or nothing Latin in it.
     */
    private static function surnameInitial(string $fullName): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');

        if ($name === '') {
            return null;
        }

        $words = explode(' ', $name);

        $surname = (new ASCII)->to_ascii((string) end($words));

        // After folding, the first letter anywhere in the token - not the first
        // character. A name written "de la Cruz" ends in Cruz, but a surname
        // beginning with a quote or an apostrophe would otherwise be skipped over
        // and give the wrong letter.
        if (preg_match('/[a-z]/i', $surname, $match) !== 1) {
            return null;
        }

        return mb_strtoupper($match[0]);
    }
}
