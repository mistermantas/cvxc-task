<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerCsvImport
{
    private const COLUMNS = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'country'];

    private const MAX_ROWS = 1000;

    /** @return array{total: int, valid: int, failed: int, rows: list<array{line: int, data: array<string, string|null>, errors: list<string>}>} */
    public function analyze(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Nepavyko perskaityti failo.']);
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');

            if (! is_array($header)) {
                throw ValidationException::withMessages(['file' => 'CSV failas tuščias.']);
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            $columns = array_map(
                fn ($value) => strtolower(str_replace([' ', '-'], '_', trim($value))),
                $header,
            );

            if (count($columns) !== count(array_unique($columns)) ||
                array_diff(self::COLUMNS, $columns) !== [] ||
                array_diff($columns, self::COLUMNS) !== []) {
                throw ValidationException::withMessages([
                    'file' => 'Reikalingi stulpeliai: '.implode(', ', self::COLUMNS).'.',
                ]);
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;

                if ($values === [null]) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Viename faile gali būti iki 1000 klientų.']);
                }

                $errors = [];
                if (count($values) !== count($columns)) {
                    $errors[] = 'Netinkamas stulpelių skaičius.';
                }

                $values = array_pad(array_slice($values, 0, count($columns)), count($columns), '');
                $data = array_combine($columns, array_map(fn ($value) => trim($value ?? ''), $values));
                $data['email'] = mb_strtolower($data['email']);
                $data['phone'] = $data['phone'] ?: null;
                $data['birth_date'] = $data['birth_date'] ?: null;

                if (! mb_check_encoding(implode('', $values), 'UTF-8')) {
                    $errors[] = 'Failas turi būti UTF-8 koduotės.';
                }

                $validator = Validator::make($data, [
                    'first_name' => ['required', 'string', 'max:100'],
                    'last_name' => ['required', 'string', 'max:100'],
                    'email' => ['required', 'email:rfc', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'country' => ['required', 'string', 'max:100'],
                ], [
                    'required' => 'Privalomas laukas: :attribute.',
                    'email' => 'Netinkamas el. pašto adresas.',
                    'date_format' => 'Data turi būti YYYY-MM-DD formato.',
                    'before_or_equal' => 'Gimimo data negali būti ateityje.',
                    'max' => 'Per ilga reikšmė: :attribute.',
                ], [
                    'first_name' => 'vardas',
                    'last_name' => 'pavardė',
                    'email' => 'el. paštas',
                    'phone' => 'telefonas',
                    'birth_date' => 'gimimo data',
                    'country' => 'šalis',
                ]);

                foreach ($validator->errors()->all() as $error) {
                    $errors[] = $error;
                }

                $rows[] = [
                    'line' => $line,
                    'data' => $data,
                    'errors' => $errors,
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'CSV faile nėra klientų eilučių.']);
            }

            $seen = [];
            $emails = [];

            foreach ($rows as $row) {
                if (filter_var($row['data']['email'], FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $row['data']['email'];
                }
            }

            $existing = [];
            foreach (array_chunk(array_unique($emails), 400) as $chunk) {
                foreach (DB::table('customers')->whereIn('email', $chunk)->pluck('email') as $email) {
                    $existing[mb_strtolower($email)] = true;
                }
            }

            foreach ($rows as &$row) {
                $email = $row['data']['email'];

                if (isset($existing[$email])) {
                    $row['errors'][] = 'El. paštas jau yra duomenų bazėje.';
                } elseif ($row['errors'] === [] && isset($seen[$email])) {
                    $row['errors'][] = 'El. paštas kartojasi šiame faile.';
                }

                if ($email !== '' && $row['errors'] === []) {
                    $seen[$email] = true;
                }
            }
            unset($row);

            $valid = count(array_filter($rows, fn ($row) => $row['errors'] === []));

            return [
                'total' => count($rows),
                'valid' => $valid,
                'failed' => count($rows) - $valid,
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }
}
