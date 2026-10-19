<!DOCTYPE html>
<html lang="{{ $isEn ? 'en' : 'fr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isEn ? 'Password Reset' : 'Réinitialisation de mot de passe' }}</title>
</head>
<body style="margin: 0; padding: 20px; background-color: #f8fafc; font-family: Arial, sans-serif;">
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
        <h2 style="color: #8E5E0A; margin-top: 0; font-size: 20px;">
            @if($isEn)
                Hello {{ $name }},
            @else
                Bonjour {{ $name }},
            @endif
        </h2>

        @if($isEn)
            <p style="color: #334155; font-size: 15px; line-height: 1.6; margin-bottom: 16px;">
                We have received a password reset request for your account on the <strong>Plan d'Épargne Kori (PEK)</strong> platform by KORI Asset Management.
            </p>
            <p style="color: #334155; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                To choose a new secure password, please click the button below:
            </p>
        @else
            <p style="color: #334155; font-size: 15px; line-height: 1.6; margin-bottom: 16px;">
                Nous avons bien reçu une demande de réinitialisation du mot de passe pour votre compte sur la plateforme <strong>Plan d'Épargne Kori (PEK)</strong> de KORI Asset Management.
            </p>
            <p style="color: #334155; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                Pour définir votre nouveau mot de passe sécurisé, veuillez cliquer sur le bouton ci-dessous :
            </p>
        @endif

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" 
               target="_blank"
               style="background-color: #8E5E0A; color: #ffffff !important; padding: 14px 28px; text-decoration: none !important; font-weight: bold; border-radius: 8px; display: inline-block; font-size: 15px; font-family: Arial, sans-serif;">
                @if($isEn)
                    Reset my password
                @else
                    Réinitialiser mon mot de passe
                @endif
            </a>
        </div>

        @if($isEn)
            <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin-bottom: 8px;">
                This link is strictly personal, single-use, and will expire in <strong>60 minutes</strong>.
            </p>
            <p style="color: #64748b; font-size: 13px; line-height: 1.5;">
                If you did not request a password reset, you can safely ignore this email: your current password remains unchanged and your account is secure.
            </p>
        @else
            <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin-bottom: 8px;">
                Ce lien est strictement personnel, à usage unique, et expirera dans <strong>60 minutes</strong>.
            </p>
            <p style="color: #64748b; font-size: 13px; line-height: 1.5;">
                Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail en toute sécurité : votre mot de passe actuel reste inchangé et votre compte est protégé.
            </p>
        @endif

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;" />

        <p style="color: #94a3b8; font-size: 12px; text-align: center; margin: 0;">
            KORI Asset Management — {{ $isEn ? 'Asset Management Company approved by COSUMAF' : 'Société de Gestion agréée COSUMAF' }}
        </p>
    </div>
</body>
</html>
