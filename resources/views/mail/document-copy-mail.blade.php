<x-mail::message>
Dear Resident,

Your requested document with reference number **{{ $referenceNumber }}** is ready.

The attached PDF is encrypted. The password is worked out from your birthdate (mm/dd/yy)
and the first letter of your surname. As an example: a resident born on 17 March 1994
with surname Cruz would use **03/17/94C**.

Please note: the actual password for your account is not included in this email for your
security. The attached copy is valid only when printed with the official signature and
dry seal of the Punong Barangay.

Thank you for your patience.

Sincerely,
{{ $barangay ?: config('app.name') }} Office
</x-mail::message>