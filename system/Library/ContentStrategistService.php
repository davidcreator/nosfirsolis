<?php

namespace System\Library;

class ContentStrategistService
{
    public function buildPack(array $input): array
    {
        $theme = trim((string) ($input['theme'] ?? 'Tema estrategico'));
        $objective = trim((string) ($input['objective'] ?? 'engajamento'));
        $pillar = trim((string) ($input['pillar'] ?? 'Seja o especialista'));
        $tone = trim((string) ($input['tone'] ?? 'confiante e humano'));
        $cta = trim((string) ($input['cta'] ?? 'Comente sua opiniao'));
        $frequency = trim((string) ($input['frequency'] ?? 'semanal'));
        $audience = trim((string) ($input['audience'] ?? 'audiencia atual'));
        $channels = array_values(array_filter(array_map('trim', (array) ($input['channels'] ?? [])), static fn ($v) => $v !== ''));

        if ($channels === []) {
            $channels = ['instagram'];
        }

        $headline = 'Plano ' . ucfirst($frequency) . ': ' . $theme;
        $baseText = $this->composeBaseText($theme, $objective, $pillar, $tone, $audience, $cta);
        $hooks = $this->hooks($theme, $objective, $pillar);
        $hashtags = $this->hashtags($theme, $objective, $pillar);

        $variants = [];
        foreach ($channels as $channel) {
            $variants[$channel] = $this->channelVariant($channel, $baseText, $cta);
        }

        return [
            'title' => $headline,
            'theme' => $theme,
            'objective' => $objective,
            'pillar' => $pillar,
            'tone' => $tone,
            'audience' => $audience,
            'frequency' => $frequency,
            'channels' => $channels,
            'base_text' => $baseText,
            'hooks' => $hooks,
            'hashtags' => $hashtags,
            'cta' => $cta,
            'variants' => $variants,
        ];
    }

    private function composeBaseText(
        string $theme,
        string $objective,
        string $pillar,
        string $tone,
        string $audience,
        string $cta
    ): string {
        return 'Tema: ' . $theme . "\n"
            . 'Objetivo: ' . $objective . "\n"
            . 'Pilar: ' . $pillar . "\n"
            . 'Tom: ' . $tone . "\n"
            . 'Publico: ' . $audience . "\n\n"
            . 'Roteiro sugerido:' . "\n"
            . '1) Contextualize a dor real do publico em ate 2 frases.' . "\n"
            . '2) Entregue um insight pratico com exemplo aplicado.' . "\n"
            . '3) Conecte com uma prova de resultado ou bastidor verdadeiro.' . "\n"
            . '4) Feche com CTA objetivo: ' . $cta . '.';
    }

    private function hooks(string $theme, string $objective, string $pillar): array
    {
        return [
            'Se voce quer melhorar ' . strtolower($objective) . ', comece por este ponto sobre ' . $theme . '.',
            'Pouca gente aplica este principio de "' . $pillar . '" para acelerar resultados.',
            'Antes de postar hoje, use este filtro rapido sobre ' . $theme . ' para ganhar clareza.',
        ];
    }

    private function hashtags(string $theme, string $objective, string $pillar): array
    {
        $normalize = static function (string $value): string {
            $value = strtolower(trim($value));
            $value = preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
            return $value;
        };

        $themeTag = $normalize($theme);
        $objectiveTag = $normalize($objective);
        $pillarTag = $normalize($pillar);

        $hashtags = ['#conteudoestrategico', '#marketingdigital', '#planejamentoanual'];

        if ($themeTag !== '') {
            $hashtags[] = '#' . $themeTag;
        }
        if ($objectiveTag !== '') {
            $hashtags[] = '#' . $objectiveTag;
        }
        if ($pillarTag !== '') {
            $hashtags[] = '#' . $pillarTag;
        }

        return array_values(array_unique($hashtags));
    }

    private function channelVariant(string $channel, string $baseText, string $cta): string
    {
        $channel = strtolower($channel);

        return match ($channel) {
            'instagram', 'threads' => $baseText . "\n\nFormato sugerido: carrossel + stories de bastidor.\nCTA final: " . $cta . '.',
            'facebook' => $baseText . "\n\nFormato sugerido: post com narrativa + imagem de prova social.",
            'linkedin' => $baseText . "\n\nFormato sugerido: post consultivo com 3 aprendizados e 1 pergunta final.",
            'tiktok', 'youtube', 'vimeo' => $baseText . "\n\nFormato sugerido: roteiro em video (gancho 3s + contexto + prova + CTA).",
            'x-twitter' => $baseText . "\n\nFormato sugerido: thread curta em 5 partes com CTA para resposta.",
            'pinterest' => $baseText . "\n\nFormato sugerido: pin com checklist visual e link de aprofundamento.",
            'blog' => $baseText . "\n\nFormato sugerido: artigo estruturado com SEO e CTA no final.",
            'podcast' => $baseText . "\n\nFormato sugerido: episodio curto com pauta em 3 blocos.",
            'email-marketing' => $baseText . "\n\nFormato sugerido: sequencia de email com assunto + historia + CTA.",
            default => $baseText,
        };
    }
}

