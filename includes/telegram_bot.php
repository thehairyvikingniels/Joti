<?php
// includes/telegram_bot.php — Official Jotify Telegram Bot Client for Bot API interactions.

class TelegramBot {
    private string $token;
    private ?mysqli $conn;

    public function __construct(?mysqli $conn = null, ?string $token = null) {
        $this->conn = $conn;
        if ($token !== null && $token !== '') {
            $this->token = trim($token);
        } elseif ($conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'TELEGRAM_BOT_TOKEN'");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $this->token = trim($row['Waarde'] ?? '');
                $stmt->close();
            } else {
                $this->token = '';
            }
        } else {
            $this->token = '';
        }
    }

    /**
     * Checks if the bot token is configured with a real value.
     */
    public function isConfigured(): bool {
        return !empty($this->token) 
            && !str_starts_with($this->token, 'placeholder') 
            && !str_starts_with($this->token, '123456789:ABC');
    }

    /**
     * Returns the currently configured bot token.
     */
    public function getToken(): string {
        return $this->token;
    }

    /**
     * Executes an HTTP POST request to the Telegram Bot API using cURL.
     *
     * @param string $method Telegram Bot API method (e.g. 'sendMessage', 'getMe')
     * @param array $params Method parameters
     * @return array Decoded response from Telegram
     */
    public function callApi(string $method, array $params = []): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error_code' => 400, 'description' => 'Telegram bot token is niet geconfigureerd of is een placeholder.'];
        }

        $url = "https://api.telegram.org/bot" . $this->token . "/" . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error_code' => 0, 'description' => 'cURL Error: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error_code' => $httpCode, 'description' => 'Ongeldig JSON antwoord van Telegram: ' . substr($response, 0, 100)];
        }

        return $decoded;
    }

    /**
     * Returns basic information about the bot (verifies token validity).
     */
    public function getMe(): array {
        return $this->callApi('getMe');
    }

    /**
     * Registers the HTTPS webhook with Telegram.
     */
    public function setWebhook(string $url, ?string $secretToken = null): array {
        $params = [
            'url' => $url,
            'allowed_updates' => ['message', 'edited_message', 'callback_query']
        ];
        if (!empty($secretToken)) {
            $params['secret_token'] = $secretToken;
        }
        return $this->callApi('setWebhook', $params);
    }

    /**
     * Removes the webhook from Telegram.
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array {
        return $this->callApi('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates
        ]);
    }

    /**
     * Retrieves current webhook status and diagnostics from Telegram.
     */
    public function getWebhookInfo(): array {
        return $this->callApi('getWebhookInfo');
    }

    /**
     * Sends a text message to a specific chat.
     */
    public function sendMessage(
        string|int $chatId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'Markdown',
        bool $disableWebPagePreview = false
    ): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disableWebPagePreview
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }
        return $this->callApi('sendMessage', $params);
    }

    /**
     * Sends a location pin to a specific chat.
     */
    public function sendLocation(string|int $chatId, float $latitude, float $longitude): array {
        return $this->callApi('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
    }
}
