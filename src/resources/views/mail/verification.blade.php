<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #121212; font-family: Arial, sans-serif;">

    <table role="presentation" width="100%" bgcolor="#121212" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" bgcolor="#1b1b1b" cellspacing="0" cellpadding="20" border="0" style="border-radius: 10px; margin-top: 20px;">
                    <!-- Logo -->
                    <tr>
                        <td align="center">
                            <img src="{{ env("APP_LOGO", "https://i.ibb.co/9mvDQj19/xwms-logo.png") }}" width="80" alt="XWMS Logo">
                        </td>
                    </tr>
                    <!-- Titel -->
                    <tr>
                        <td align="center" style="color: #ffffff; font-size: 24px; font-weight: bold;">
                            {{ $data['subject'] }}
                        </td>
                    </tr>

                    <!-- Beschrijving -->
                    <tr>
                        <td align="center" style="color: #cccccc; font-size: 16px;">
                            @if ($data['name'])
                                Hello <strong>{{ $data['name'] }}</strong>, <br>
                            @endif
                            {{ $data['description_short'] }}
                        </td>
                    </tr>

                    <!-- Beveiligingscode -->
                    <tr>
                        <td align="center" bgcolor="#252525" style="padding: 20px; border-radius: 5px;">
                            <span style="color: #b66dff; font-size: 32px; font-weight: bold;">{{ $data['verificationCode'] }}</span>
                        </td>
                    </tr>

                    <!-- Extra melding -->
                    <tr>
                        <td align="center" style="color: #cccccc; font-size: 14px;">
                            {{ $data['description'] }}
                        </td>
                    </tr>

                    <!-- Alternatieve afsluiting -->
                    <tr>
                        <td align="center" style="color: #888888; font-size: 12px; padding-top: 10px;">
                            {{ $data['description_second'] }}
                        </td>
                    </tr>

                    <!-- Footer -->
                    @if ($data['show_xwms'])
                        <tr>
                            <td align="center" style="padding-top: 20px;">
                                <img src="https://i.ibb.co/9mvDQj19/xwms-logo.png" width="30" alt="XWMS Logo">
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
