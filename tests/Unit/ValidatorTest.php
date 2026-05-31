<?php
declare(strict_types=1);

namespace Tests\Unit;

use Flint\Validator;
use Flint\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private function validate(array $data, array $rules): array
    {
        return (new Validator($data))->validate($rules);
    }

    private function assertFails(array $data, array $rules, string $field): void
    {
        try {
            $this->validate($data, $rules);
            $this->fail("Expected ValidationException for field [{$field}] but none was thrown.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    // required

    public function test_required_passes_when_present(): void
    {
        $result = $this->validate(['name' => 'Dan'], ['name' => 'required']);
        $this->assertSame('Dan', $result['name']);
    }

    public function test_required_fails_when_missing(): void
    {
        $this->assertFails([], ['name' => 'required'], 'name');
    }

    public function test_required_fails_when_empty_string(): void
    {
        $this->assertFails(['name' => ''], ['name' => 'required'], 'name');
    }

    // string

    public function test_string_passes_for_string_value(): void
    {
        $result = $this->validate(['name' => 'hello'], ['name' => 'string']);
        $this->assertSame('hello', $result['name']);
    }

    public function test_string_fails_for_integer(): void
    {
        $this->assertFails(['name' => 42], ['name' => 'string'], 'name');
    }

    // email

    public function test_email_passes_for_valid_email(): void
    {
        $result = $this->validate(['email' => 'dan@example.com'], ['email' => 'email']);
        $this->assertSame('dan@example.com', $result['email']);
    }

    public function test_email_fails_for_invalid_email(): void
    {
        $this->assertFails(['email' => 'not-an-email'], ['email' => 'email'], 'email');
    }

    // min / max

    public function test_min_passes_for_long_enough_string(): void
    {
        $result = $this->validate(['pass' => 'password'], ['pass' => 'min:6']);
        $this->assertSame('password', $result['pass']);
    }

    public function test_min_fails_for_short_string(): void
    {
        $this->assertFails(['pass' => 'abc'], ['pass' => 'min:6'], 'pass');
    }

    public function test_max_passes_for_short_enough_string(): void
    {
        $result = $this->validate(['name' => 'Dan'], ['name' => 'max:10']);
        $this->assertSame('Dan', $result['name']);
    }

    public function test_max_fails_for_long_string(): void
    {
        $this->assertFails(['name' => 'This is way too long'], ['name' => 'max:5'], 'name');
    }

    public function test_min_passes_for_numeric_value(): void
    {
        $result = $this->validate(['score' => 10], ['score' => 'min:5']);
        $this->assertSame(10, $result['score']);
    }

    public function test_max_fails_for_numeric_value(): void
    {
        $this->assertFails(['score' => 100], ['score' => 'max:50'], 'score');
    }

    // numeric

    public function test_numeric_passes_for_number(): void
    {
        $result = $this->validate(['score' => '42'], ['score' => 'numeric']);
        $this->assertSame('42', $result['score']);
    }

    public function test_numeric_fails_for_string(): void
    {
        $this->assertFails(['score' => 'abc'], ['score' => 'numeric'], 'score');
    }

    // in

    public function test_in_passes_for_listed_value(): void
    {
        $result = $this->validate(['role' => 'admin'], ['role' => 'in:admin,user,editor']);
        $this->assertSame('admin', $result['role']);
    }

    public function test_in_fails_for_unlisted_value(): void
    {
        $this->assertFails(['role' => 'superuser'], ['role' => 'in:admin,user'], 'role');
    }

    // nullable

    public function test_nullable_skips_rules_when_absent(): void
    {
        $result = $this->validate([], ['bio' => 'nullable|string']);
        $this->assertArrayNotHasKey('bio', $result);
    }

    public function test_nullable_skips_rules_when_empty(): void
    {
        $result = $this->validate(['bio' => ''], ['bio' => 'nullable|string']);
        $this->assertArrayNotHasKey('bio', $result);
    }

    public function test_nullable_validates_when_present(): void
    {
        $result = $this->validate(['bio' => 'hello'], ['bio' => 'nullable|string']);
        $this->assertSame('hello', $result['bio']);
    }

    // confirmed

    public function test_confirmed_passes_when_fields_match(): void
    {
        $result = $this->validate(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );
        $this->assertSame('secret', $result['password']);
    }

    public function test_confirmed_fails_when_fields_differ(): void
    {
        $this->assertFails(
            ['password' => 'secret', 'password_confirmation' => 'wrong'],
            ['password' => 'confirmed'],
            'password'
        );
    }

    // multiple rules

    public function test_multiple_rules_all_applied(): void
    {
        $result = $this->validate(
            ['email' => 'dan@example.com'],
            ['email' => 'required|email']
        );
        $this->assertSame('dan@example.com', $result['email']);
    }

    public function test_validation_exception_contains_all_failing_fields(): void
    {
        try {
            $this->validate(
                ['email' => 'bad', 'name' => ''],
                ['email' => 'email', 'name' => 'required']
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertArrayHasKey('name', $e->errors());
        }
    }
}
