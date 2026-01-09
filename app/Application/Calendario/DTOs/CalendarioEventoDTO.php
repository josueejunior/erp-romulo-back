<?php

declare(strict_types=1);

namespace App\Application\Calendario\DTOs;

/**
 * DTO de saída para evento do calendário
 */
final class CalendarioEventoDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $identificador,
        public readonly string $modalidade,
        public readonly ?string $numeroModalidade,
        public readonly ?string $orgao,
        public readonly ?string $uasg,
        public readonly ?string $setor,
        public readonly ?string $dataHoraSessao,
        public readonly ?string $horarioSessao,
        public readonly ?string $objetoResumido,
        public readonly ?string $linkEdital,
        public readonly ?string $portal,
        public readonly array $precosMinimos,
        public readonly int $totalItens,
        public readonly ?int $diasRestantes,
        public readonly string $statusParticipacao,
        public readonly array $avisos,
    ) {}

    /**
     * Cria DTO a partir de um array (processo formatado)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            identificador: $data['identificador'],
            modalidade: $data['modalidade'],
            numeroModalidade: $data['numero_modalidade'] ?? null,
            orgao: $data['orgao'] ?? null,
            uasg: $data['uasg'] ?? null,
            setor: $data['setor'] ?? null,
            dataHoraSessao: $data['data_hora_sessao'] ?? null,
            horarioSessao: $data['horario_sessao'] ?? null,
            objetoResumido: $data['objeto_resumido'] ?? null,
            linkEdital: $data['link_edital'] ?? null,
            portal: $data['portal'] ?? null,
            precosMinimos: $data['precos_minimos'] ?? [],
            totalItens: $data['total_itens'] ?? 0,
            diasRestantes: $data['dias_restantes'] ?? null,
            statusParticipacao: $data['status_participacao'] ?? 'normal',
            avisos: $data['avisos'] ?? [],
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identificador' => $this->identificador,
            'modalidade' => $this->modalidade,
            'numero_modalidade' => $this->numeroModalidade,
            'orgao' => $this->orgao,
            'uasg' => $this->uasg,
            'setor' => $this->setor,
            'data_hora_sessao' => $this->dataHoraSessao,
            'horario_sessao' => $this->horarioSessao,
            'objeto_resumido' => $this->objetoResumido,
            'link_edital' => $this->linkEdital,
            'portal' => $this->portal,
            'precos_minimos' => $this->precosMinimos,
            'total_itens' => $this->totalItens,
            'dias_restantes' => $this->diasRestantes,
            'status_participacao' => $this->statusParticipacao,
            'avisos' => $this->avisos,
        ];
    }
}



