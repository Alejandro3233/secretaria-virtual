<?php

namespace App\Services;

use App\Models\Clinic;
use Carbon\CarbonInterface;

class NoraLanguageService
{
    private const COUNTRY_LANGUAGES = [
        'US' => 'en', 'CA' => 'en', 'GB' => 'en', 'AU' => 'en', 'NZ' => 'en', 'IE' => 'en',
        'ES' => 'es', 'MX' => 'es', 'CO' => 'es', 'AR' => 'es', 'CL' => 'es', 'PE' => 'es',
        'VE' => 'es', 'EC' => 'es', 'BO' => 'es', 'PY' => 'es', 'UY' => 'es', 'CR' => 'es',
        'PA' => 'es', 'GT' => 'es', 'HN' => 'es', 'SV' => 'es', 'NI' => 'es', 'DO' => 'es', 'PR' => 'es',
        'FR' => 'fr', 'BE' => 'fr', 'MC' => 'fr',
        'PT' => 'pt', 'BR' => 'pt',
    ];

    private const PHONE_PREFIX_COUNTRIES = [
        '351' => 'PT', '376' => 'ES', '502' => 'GT', '503' => 'SV', '504' => 'HN', '505' => 'NI',
        '506' => 'CR', '507' => 'PA', '591' => 'BO', '593' => 'EC', '595' => 'PY', '598' => 'UY',
        '34' => 'ES', '33' => 'FR', '44' => 'GB', '52' => 'MX', '54' => 'AR', '55' => 'BR',
        '56' => 'CL', '57' => 'CO', '58' => 'VE', '51' => 'PE', '1' => 'US',
    ];

    private const TEXTS = [
        'es' => [
            'thanks' => 'Gracias por llamar.', 'see_you' => 'Nos vemos pronto en el salón.',
            'invalid' => 'No pudimos validar esta llamada. Por favor contacta al salón.',
            'kept' => 'Gracias. Tu cita se mantiene confirmada. Nos vemos pronto en el salón.',
            'sms_sent' => 'Te enviamos un mensaje de texto con el enlace para reagendar tu cita. Gracias.',
            'sms_failed' => 'No pudimos enviar el mensaje de texto. Por favor contacta al salón.',
            'unknown_clinic' => 'Hola, gracias por llamar. Soy Nora, la asistente virtual del salón. No pude identificar la línea. Por favor intenta de nuevo más tarde.',
            'unknown_client' => 'Hola, gracias por llamar a :clinic. Soy Nora, la asistente virtual. No encontré una cita asociada a este número. Por favor llama al salón para que podamos ayudarte.',
            'no_appointment' => 'Hola :name, gracias por llamar a :clinic. Soy Nora. No encontré una cita próxima asociada a tu número. Por favor llama al salón para que podamos ayudarte.',
            'appointment' => 'Hola :name, gracias por llamar a :clinic. Soy Nora. Encontré tu próxima cita: :datetime. Si necesitas cambiarla o cancelarla, presiona 1 y te enviaremos un SMS para reagendarla.',
            'reminder' => 'Hola :name, soy Nora, la asistente virtual de :clinic. Te recordamos tu cita para :datetime. Si necesitas modificarla, presiona 1 y te enviaremos un SMS.',
            'reschedule_sms' => ':clinic: usa este enlace para reagendar tu cita: :link',
        ],
        'en' => [
            'thanks' => 'Thank you for calling.', 'see_you' => 'We look forward to seeing you soon.',
            'invalid' => 'We could not validate this call. Please contact the salon.',
            'kept' => 'Thank you. Your appointment remains confirmed. We look forward to seeing you soon.',
            'sms_sent' => 'We sent you a text message with a link to reschedule your appointment. Thank you.',
            'sms_failed' => 'We could not send the text message. Please contact the salon.',
            'unknown_clinic' => 'Hello, thank you for calling. I am Nora, the virtual assistant. I could not identify this line. Please try again later.',
            'unknown_client' => 'Hello, thank you for calling :clinic. I am Nora, the virtual assistant. I could not find an appointment linked to this number. Please contact the salon for assistance.',
            'no_appointment' => 'Hello :name, thank you for calling :clinic. I am Nora. I could not find an upcoming appointment linked to your number. Please contact the salon for assistance.',
            'appointment' => 'Hello :name, thank you for calling :clinic. I am Nora. I found your next appointment: :datetime. To change or cancel it, press 1 and we will text you a rescheduling link.',
            'reminder' => 'Hello :name, I am Nora, the virtual assistant for :clinic. This is a reminder of your appointment on :datetime. To change it, press 1 and we will send you a text message.',
            'reschedule_sms' => ':clinic: use this link to reschedule your appointment: :link',
        ],
        'fr' => [
            'thanks' => 'Merci de votre appel.', 'see_you' => 'À bientôt au salon.',
            'invalid' => 'Nous n’avons pas pu valider cet appel. Veuillez contacter le salon.',
            'kept' => 'Merci. Votre rendez-vous reste confirmé. À bientôt au salon.',
            'sms_sent' => 'Nous vous avons envoyé un SMS avec le lien pour déplacer votre rendez-vous. Merci.',
            'sms_failed' => 'Nous n’avons pas pu envoyer le SMS. Veuillez contacter le salon.',
            'unknown_clinic' => 'Bonjour et merci de votre appel. Je suis Nora, l’assistante virtuelle. Je n’ai pas pu identifier cette ligne. Veuillez réessayer plus tard.',
            'unknown_client' => 'Bonjour et merci d’appeler :clinic. Je suis Nora, l’assistante virtuelle. Je n’ai trouvé aucun rendez-vous associé à ce numéro. Veuillez contacter le salon.',
            'no_appointment' => 'Bonjour :name, merci d’appeler :clinic. Je suis Nora. Je n’ai trouvé aucun prochain rendez-vous associé à votre numéro. Veuillez contacter le salon.',
            'appointment' => 'Bonjour :name, merci d’appeler :clinic. Je suis Nora. Votre prochain rendez-vous est prévu :datetime. Pour le modifier ou l’annuler, appuyez sur 1 et nous vous enverrons un SMS.',
            'reminder' => 'Bonjour :name, je suis Nora, l’assistante virtuelle de :clinic. Nous vous rappelons votre rendez-vous prévu :datetime. Pour le modifier, appuyez sur 1.',
            'reschedule_sms' => ':clinic : utilisez ce lien pour déplacer votre rendez-vous : :link',
        ],
        'pt' => [
            'thanks' => 'Obrigado por ligar.', 'see_you' => 'Esperamos você em breve no salão.',
            'invalid' => 'Não foi possível validar esta chamada. Entre em contato com o salão.',
            'kept' => 'Obrigado. Seu horário continua confirmado. Esperamos você em breve.',
            'sms_sent' => 'Enviamos uma mensagem com o link para remarcar seu horário. Obrigado.',
            'sms_failed' => 'Não foi possível enviar a mensagem. Entre em contato com o salão.',
            'unknown_clinic' => 'Olá, obrigado por ligar. Sou Nora, a assistente virtual. Não consegui identificar esta linha. Tente novamente mais tarde.',
            'unknown_client' => 'Olá, obrigado por ligar para :clinic. Sou Nora, a assistente virtual. Não encontrei um horário associado a este número. Entre em contato com o salão.',
            'no_appointment' => 'Olá :name, obrigado por ligar para :clinic. Sou Nora. Não encontrei um próximo horário associado ao seu número. Entre em contato com o salão.',
            'appointment' => 'Olá :name, obrigado por ligar para :clinic. Sou Nora. Seu próximo horário é :datetime. Para alterar ou cancelar, pressione 1 e enviaremos um link por mensagem.',
            'reminder' => 'Olá :name, sou Nora, a assistente virtual de :clinic. Lembramos que seu horário está marcado para :datetime. Para alterar, pressione 1.',
            'reschedule_sms' => ':clinic: use este link para remarcar seu horário: :link',
        ],
    ];

    public function language(?Clinic $clinic): string
    {
        return 'es';
    }

    public function detectedLanguage(?string $phone, ?string $savedCountry = null): string
    {
        $saved = strtoupper((string) $savedCountry);
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (str_starts_with($digits, '1') && in_array($saved, ['US', 'CA'], true)) {
            return self::COUNTRY_LANGUAGES[$saved];
        }

        foreach (self::PHONE_PREFIX_COUNTRIES as $prefix => $country) {
            if (str_starts_with($digits, $prefix)) {
                return self::COUNTRY_LANGUAGES[$country] ?? 'es';
            }
        }

        return self::COUNTRY_LANGUAGES[$saved] ?? 'es';
    }

    public function text(?Clinic $clinic, string $key, array $replace = []): string
    {
        $text = self::TEXTS[$this->language($clinic)][$key] ?? self::TEXTS['es'][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }
        return $text;
    }

    public function dateTime(CarbonInterface $date, ?Clinic $clinic): string
    {
        $local = $date->copy()->timezone($clinic?->localTimezone() ?: config('app.timezone'));
        $locale = ['es' => 'es', 'en' => 'en', 'fr' => 'fr', 'pt' => 'pt_BR'][$this->language($clinic)] ?? 'es';
        return $local->locale($locale)->isoFormat('dddd, D MMMM YYYY, LT');
    }
}
