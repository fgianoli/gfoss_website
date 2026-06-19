<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Validatori.
 *
 *   Validator::check( $input, $rules )
 *   restituisce [ $clean, $errors ].
 */
class Validator {

    /** Codice fiscale italiano: formato + checksum carattere 16. */
    public static function is_codice_fiscale( string $cf ): bool {
        $cf = strtoupper( trim( $cf ) );
        if ( ! preg_match( '/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/', $cf ) ) {
            return false;
        }

        $odd = [
            '0'=>1,'1'=>0,'2'=>5,'3'=>7,'4'=>9,'5'=>13,'6'=>15,'7'=>17,'8'=>19,'9'=>21,
            'A'=>1,'B'=>0,'C'=>5,'D'=>7,'E'=>9,'F'=>13,'G'=>15,'H'=>17,'I'=>19,'J'=>21,
            'K'=>2,'L'=>4,'M'=>18,'N'=>20,'O'=>11,'P'=>3,'Q'=>6,'R'=>8,'S'=>12,'T'=>14,
            'U'=>16,'V'=>10,'W'=>22,'X'=>25,'Y'=>24,'Z'=>23,
        ];
        $even = [];
        for ( $i = 0; $i <= 9; $i++ ) { $even[ (string) $i ] = $i; }
        for ( $i = 0; $i <= 25; $i++ ) { $even[ chr( 65 + $i ) ] = $i; }

        $sum = 0;
        for ( $i = 0; $i < 15; $i++ ) {
            $c = $cf[ $i ];
            $sum += ( $i % 2 === 0 ) ? $odd[ $c ] : $even[ $c ];
        }
        return chr( 65 + ( $sum % 26 ) ) === $cf[15];
    }

    public static function check( array $input, array $rules ): array {
        $clean = []; $errors = [];

        foreach ( $rules as $field => $rule ) {
            $raw = isset( $input[ $field ] ) ? wp_unslash( $input[ $field ] ) : '';
            $value = is_string( $raw ) ? trim( $raw ) : $raw;

            // required
            if ( ! empty( $rule['required'] ) && ( $value === '' || $value === null ) ) {
                $errors[ $field ] = $rule['label'] . ' è obbligatorio.';
                continue;
            }
            // accepted (checkbox 1/on/true)
            if ( ! empty( $rule['accepted'] ) ) {
                if ( ! in_array( (string) $value, [ '1', 'on', 'true', 'yes' ], true ) ) {
                    $errors[ $field ] = 'Devi accettare ' . strtolower( $rule['label'] ) . '.';
                    continue;
                }
                $clean[ $field ] = 1; continue;
            }
            // optional + empty → skip
            if ( $value === '' && empty( $rule['required'] ) ) {
                $clean[ $field ] = null; continue;
            }

            switch ( $rule['type'] ?? 'text' ) {
                case 'email':
                    if ( ! is_email( $value ) ) {
                        $errors[ $field ] = $rule['label'] . ' non è una email valida.';
                    } else {
                        $clean[ $field ] = sanitize_email( $value );
                    }
                    break;
                case 'codice_fiscale':
                    $up = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) );
                    if ( ! self::is_codice_fiscale( $up ) ) {
                        $errors[ $field ] = $rule['label'] . ' non è valido.';
                    } else {
                        $clean[ $field ] = $up;
                    }
                    break;
                case 'date':
                    $d = \DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $value );
                    if ( ! $d || $d->format( 'Y-m-d' ) !== $value ) {
                        $errors[ $field ] = $rule['label'] . ' non è una data valida (YYYY-MM-DD).';
                    } else {
                        $clean[ $field ] = $value;
                    }
                    break;
                case 'cap':
                    if ( ! preg_match( '/^\d{5}$/', (string) $value ) ) {
                        $errors[ $field ] = $rule['label'] . ' deve essere di 5 cifre.';
                    } else { $clean[ $field ] = $value; }
                    break;
                case 'provincia':
                    $p = strtoupper( (string) $value );
                    if ( ! preg_match( '/^[A-Z]{2}$/', $p ) ) {
                        $errors[ $field ] = $rule['label'] . ' deve essere la sigla a 2 lettere (es. PD).';
                    } else { $clean[ $field ] = $p; }
                    break;
                case 'phone':
                    if ( ! preg_match( '/^[+0-9\s().\-]{6,30}$/', (string) $value ) ) {
                        $errors[ $field ] = $rule['label'] . ' non è un numero valido.';
                    } else { $clean[ $field ] = preg_replace( '/\s+/', ' ', (string) $value ); }
                    break;
                case 'textarea':
                    $clean[ $field ] = sanitize_textarea_field( (string) $value );
                    if ( ! empty( $rule['max'] ) && mb_strlen( $clean[ $field ] ) > $rule['max'] ) {
                        $errors[ $field ] = $rule['label'] . " non può superare {$rule['max']} caratteri.";
                    }
                    break;
                default:
                    $clean[ $field ] = sanitize_text_field( (string) $value );
                    if ( ! empty( $rule['max'] ) && mb_strlen( $clean[ $field ] ) > $rule['max'] ) {
                        $errors[ $field ] = $rule['label'] . " non può superare {$rule['max']} caratteri.";
                    }
            }
        }
        return [ $clean, $errors ];
    }
}
