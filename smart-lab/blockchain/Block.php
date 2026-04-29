<?php
class Block {
    public int    $index;
    public string $timestamp;
    public array  $data;
    public string $previousHash;
    public string $hash;
    public int    $nonce = 0;

    public function __construct(int $index, array $data, string $previousHash = '0') {
        $this->index        = $index;
        $this->timestamp    = date('Y-m-d H:i:s');
        $this->data         = $data;
        $this->previousHash = $previousHash;
        $this->hash         = $this->calculateHash();
    }
    public function calculateHash(): string {
        return hash('sha256',
            $this->index.$this->timestamp.
            json_encode($this->data).
            $this->previousHash.$this->nonce
        );
    }
    public function mine(int $difficulty): void {
        $target = str_repeat('0', $difficulty);
        while (substr($this->hash, 0, $difficulty) !== $target) {
            $this->nonce++;
            $this->hash = $this->calculateHash();
        }
    }
}
