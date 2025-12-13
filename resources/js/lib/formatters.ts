/**
 * Brazilian Document Formatters
 *
 * Utilities for formatting CPF, CNPJ, CEP, and phone numbers.
 * These are used across billing forms in both signup and tenant checkout.
 */

/**
 * Format CPF (11 digits) or CNPJ (14 digits).
 *
 * CPF format: 000.000.000-00
 * CNPJ format: 00.000.000/0000-00
 *
 * @param value - Raw or partially formatted value
 * @returns Formatted CPF or CNPJ string
 */
export function formatCpfCnpj(value: string): string {
    const digits = value.replace(/\D/g, '');

    if (digits.length <= 11) {
        // CPF: 000.000.000-00
        return digits
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    // CNPJ: 00.000.000/0000-00
    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2')
        .slice(0, 18);
}

/**
 * Remove formatting from CPF/CNPJ, keeping only digits.
 *
 * @param value - Formatted CPF/CNPJ string
 * @returns Raw digits only
 */
export function unformatCpfCnpj(value: string): string {
    return value.replace(/\D/g, '');
}

/**
 * Validate CPF/CNPJ format (not checksum validation).
 *
 * @param value - Raw or formatted CPF/CNPJ
 * @returns true if length is valid (11 for CPF, 14 for CNPJ)
 */
export function isValidCpfCnpjLength(value: string): boolean {
    const digits = value.replace(/\D/g, '');
    return digits.length === 11 || digits.length === 14;
}

/**
 * Format Brazilian postal code (CEP).
 *
 * CEP format: 00000-000
 *
 * @param value - Raw or partially formatted value
 * @returns Formatted CEP string
 */
export function formatCep(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 8);
    return digits.replace(/(\d{5})(\d)/, '$1-$2');
}

/**
 * Remove formatting from CEP, keeping only digits.
 *
 * @param value - Formatted CEP string
 * @returns Raw digits only
 */
export function unformatCep(value: string): string {
    return value.replace(/\D/g, '');
}

/**
 * Format Brazilian phone number.
 *
 * Mobile format: (00) 00000-0000
 * Landline format: (00) 0000-0000
 *
 * @param value - Raw or partially formatted value
 * @returns Formatted phone string
 */
export function formatPhone(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 10) {
        // Landline: (00) 0000-0000
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    // Mobile: (00) 00000-0000
    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

/**
 * Remove formatting from phone, keeping only digits.
 *
 * @param value - Formatted phone string
 * @returns Raw digits only
 */
export function unformatPhone(value: string): string {
    return value.replace(/\D/g, '');
}

/**
 * Format credit card number with spaces.
 *
 * Format: 0000 0000 0000 0000
 *
 * @param value - Raw or partially formatted value
 * @returns Formatted card number string
 */
export function formatCardNumber(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 19);
    return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
}

/**
 * Remove formatting from card number, keeping only digits.
 *
 * @param value - Formatted card number string
 * @returns Raw digits only
 */
export function unformatCardNumber(value: string): string {
    return value.replace(/\D/g, '');
}
