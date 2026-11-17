{{-- E-mail de confirmação de conta, nas cores da marca (marinho #0B1F3A, azul #1E4FD8, azul claro #5AA9FF).
     Estilos inline e layout em tabelas para funcionar no Gmail/Outlook. A logo vai embutida (CID), então aparece
     mesmo com o app rodando em localhost. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $assunto }}</title>
</head>
<body style="margin:0;padding:0;background:#EEF3FB;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Confirme seu e-mail para ativar sua conta no {{ $app }}.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF3FB;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
        <tr>
          <td align="center" style="padding:0 0 24px;">
            <img src="{{ $message->embed(public_path('brand/logo-email.png')) }}" width="220" alt="{{ $app }}" style="display:block;width:220px;max-width:100%;height:auto;border:0;">
          </td>
        </tr>
        <tr>
          <td style="background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(11,31,58,.08);">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="height:6px;line-height:6px;font-size:0;background:#1E4FD8;background-image:linear-gradient(90deg,#1E4FD8,#5AA9FF);">&nbsp;</td></tr>
              <tr>
                <td style="padding:36px 40px 8px;font-family:Arial,Helvetica,sans-serif;color:#0B1F3A;">
                  <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:800;color:#0B1F3A;">Olá, {{ $nome }}!</h1>
                  <p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#334155;">Recebemos o pedido de criação da sua conta no <strong style="color:#1E4FD8;">{{ $app }}</strong>.</p>
                  <p style="margin:0 0 28px;font-size:16px;line-height:1.6;color:#334155;">Para ativar o acesso, confirme seu e-mail clicando no botão abaixo.</p>
                </td>
              </tr>
              <tr>
                <td align="center" style="padding:0 40px 28px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td align="center" bgcolor="#1E4FD8" style="border-radius:12px;">
                        <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:15px 32px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:12px;">Confirmar minha conta</a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:0 40px 32px;font-family:Arial,Helvetica,sans-serif;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF3FB;border-left:4px solid #5AA9FF;border-radius:8px;">
                    <tr>
                      <td style="padding:14px 16px;font-size:14px;line-height:1.55;color:#334155;">
                        O link vale por <strong>{{ $minutos }} minutos</strong>. Depois de confirmar, é só entrar com o e-mail e a senha que você criou.
                      </td>
                    </tr>
                  </table>
                  <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#64748B;">Se você não pediu esta conta, ignore este e-mail. Nada será criado sem a confirmação.</p>
                  <p style="margin:20px 0 0;font-size:15px;line-height:1.6;color:#0B1F3A;">Equipe <strong>{{ $app }}</strong></p>
                </td>
              </tr>
              <tr>
                <td style="padding:20px 40px 28px;border-top:1px solid #E2E8F0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#64748B;">
                  Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                  <a href="{{ $url }}" target="_blank" style="color:#1E4FD8;word-break:break-all;">{{ $url }}</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#64748B;">
            © {{ date('Y') }} {{ $app }}. Organize seu dinheiro.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
