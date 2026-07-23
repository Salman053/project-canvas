<?php

declare(strict_types=1);

namespace Salman053\Canvas\Server;

use RuntimeException;

class WebSocketServer
{
    private mixed $socket = null;

    private array $clients = [];

    private bool $running = false;

    private string $host;

    private int $port;

    private array $buffer = [];

    public function __construct(string $host = '127.0.0.1', int $port = 8081)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function start(): void
    {
        $this->socket = @stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
        );

        if (! $this->socket) {
            throw new RuntimeException(
                "Failed to bind WebSocket server: {$errstr} ({$errno})",
            );
        }

        stream_set_blocking($this->socket, false);
        $this->running = true;

        $this->log("Canvas WebSocket server started on {$this->host}:{$this->port}");

        while ($this->running) {
            $this->acceptNewConnections();
            $this->handleClientMessages();
            $this->processOutgoingBuffer();
            usleep(50000);
        }
    }

    public function stop(): void
    {
        $this->running = false;

        foreach ($this->clients as $client) {
            if (is_resource($client)) {
                fclose($client);
            }
        }

        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    public function broadcast(array $data): void
    {
        $this->buffer[] = $data;
    }

    public function sendTestUpdate(string $testName, bool $passed, string $componentId): void
    {
        $this->broadcast([
            'type' => 'test_update',
            'testName' => $testName,
            'passed' => $passed,
            'componentId' => $componentId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sendTestBatch(array $results): void
    {
        $this->broadcast([
            'type' => 'test_batch',
            'results' => $results,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function acceptNewConnections(): void
    {
        if (! is_resource($this->socket)) {
            return;
        }

        $client = @stream_socket_accept($this->socket, 0);

        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);

        $header = fread($client, 4096);

        if ($header === false || $header === '') {
            fclose($client);

            return;
        }

        if ($this->performHandshake($header, $client)) {
            $this->clients[(int) $client] = $client;
            $this->log('Client connected');
        } else {
            fclose($client);
        }
    }

    private function performHandshake(string $header, mixed $client): bool
    {
        if (! preg_match('/Sec-WebSocket-Key:\s(.+)\r\n/', $header, $matches)) {
            return false;
        }

        $key = trim($matches[1]);
        $acceptKey = base64_encode(
            pack('H*', hash('sha1', $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')),
        );

        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

        fwrite($client, $response);

        return true;
    }

    private function handleClientMessages(): void
    {
        foreach ($this->clients as $id => $client) {
            if (! is_resource($client)) {
                unset($this->clients[$id]);

                continue;
            }

            $data = @fread($client, 4096);

            if ($data === false || $data === '') {
                continue;
            }

            $decoded = $this->decodeFrame($data);

            if ($decoded !== null) {
                $this->processMessage($decoded, $client);
            }
        }
    }

    private function decodeFrame(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        $opcode = $firstByte & 0x0F;

        if ($opcode === 8) {
            return null;
        }

        $masked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            $payloadLength = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            $payloadLength = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        $mask = $masked ? substr($data, $offset, 4) : null;
        $offset += $masked ? 4 : 0;
        $payload = substr($data, $offset, $payloadLength);

        if ($mask) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }

        return $payload;
    }

    private function processMessage(string $message, mixed $client): void
    {
        $data = json_decode($message, true);

        if (! $data || ! isset($data['type'])) {
            return;
        }

        match ($data['type']) {
            'ping' => $this->sendFrame($client, json_encode(['type' => 'pong'])),
            'subscribe_tests' => $this->log('Client subscribed to test updates'),
            'request_graph' => $this->log('Client requested graph data'),
            default => null,
        };
    }

    private function processOutgoingBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $disconnected = [];

        foreach ($this->clients as $id => $client) {
            if (! is_resource($client)) {
                $disconnected[] = $id;

                continue;
            }

            foreach ($this->buffer as $message) {
                $this->sendFrame($client, json_encode($message));
            }
        }

        $this->buffer = [];

        foreach ($disconnected as $id) {
            unset($this->clients[$id]);
        }
    }

    private function sendFrame(mixed $client, string $data): void
    {
        if (! is_resource($client)) {
            return;
        }

        $length = strlen($data);
        $frame = chr(0x81);

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126).pack('n', $length);
        } else {
            $frame .= chr(127).pack('J', $length);
        }

        $frame .= $data;
        @fwrite($client, $frame);
    }

    private function log(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        fwrite(STDOUT, "[{$timestamp}] Canvas: {$message}".PHP_EOL);
    }
}
