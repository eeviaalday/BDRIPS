<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DigitalCopyPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The password a resident opens their copy with.
 *
 * WHY THIS FILE IS ALL EXAMPLES
 *
 * The whole feature stands or falls on a resident being able to work out this
 * password from what they already know without asking anybody. That means every
 * shape of name and birthdate has to produce the same predictable string, so the
 * rules are stated as a table of inputs and outputs instead of as prose: a
 * sentence can be misread, a row of examples cannot.
 *
 * The three cases that are easy to get wrong, and that the data provider is
 * built around:
 * - the letter comes from the LAST word of the name, because Filipino names put
 *   the family name last but a field labelled "surname" may hold either, and the
 *   resident has to be told one rule that is always true of the name on file;
 * - the birthdate is mm/dd/yy in American order, because the office is in the
 *   Philippines, and a swapped day and month would be a resident being told the
 *   wrong password rather than the right one;
 * - accents are folded before the letter is taken, so a resident whose name is
 *   written with a diacritic gets a password based on the plain letter, and the
 *   two are the same password - not one that fails because their keyboard could
 *   not produce the accented form.
 */
class DigitalCopyPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function names(): array
    {
        return [
            'one name takes the first and only letter' => ['Maria', '03/17/94M'],
            'family name last' => ['Juan Dela Cruz Santos', '03/17/94S'],
            'three names, the last still decides' => ['Ana Maria Bautista', '03/17/94B'],
            'a suffix is treated as part of the name' => ['Jose Reyes Jr', '03/17/94J'],
            'an accent folds to its plain letter' => ['Nuñez', '03/17/94N'],
            'a leading space is ignored' => ['  Mendoza', '03/17/94M'],
            'trailing space is ignored' => ['Mendoza   ', '03/17/94M'],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function the_password_is_the_birthdate_then_the_first_letter_of_the_last_name(
        string $name,
        string $expected,
    ): void {
        $user = User::factory()->resident()->create([
            'name' => $name,
            'date_of_birth' => '1994-03-17',
        ]);

        $this->assertSame($expected, DigitalCopyPassword::for($user));
    }

    #[Test]
    public function the_letter_is_uppercase_and_the_date_is_zero_padded(): void
    {
        $user = User::factory()->resident()->create([
            'name' => 'mendoza',
            'date_of_birth' => '2001-01-05',
        ]);

        $this->assertSame('01/05/01M', DigitalCopyPassword::for($user));
    }

    /**
     * The cases where no password can be made.
     *
     * These return null rather than a guess on purpose. A resident with no
     * birthdate on file has two mm/dd/yy strings that look equally plausible, and
     * a wrong password is indistinguishable from a wrong copy - so the honest
     * answer is to send them to the hall instead of issuing something.
     */
    #[Test]
    public function there_is_no_password_without_a_birthdate(): void
    {
        $user = User::factory()->resident()->create([
            'name' => 'Mendoza',
            'date_of_birth' => null,
        ]);

        $this->assertNull(DigitalCopyPassword::for($user));
    }

    #[Test]
    public function there_is_no_password_when_the_name_has_no_letter_in_its_last_word(): void
    {
        $user = User::factory()->resident()->create([
            'name' => 'de la Cruz 123',
            'date_of_birth' => '1994-03-17',
        ]);

        $this->assertNull(DigitalCopyPassword::for($user));
    }

    #[Test]
    public function there_is_no_password_for_a_missing_account(): void
    {
        $this->assertNull(DigitalCopyPassword::for(null));
    }
}
