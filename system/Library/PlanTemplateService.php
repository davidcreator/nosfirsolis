<?php

namespace System\Library;

use DateTimeImmutable;

class PlanTemplateService
{
    public function templates(): array
    {
        return [
            $this->template('b2c-comercial', 'Plano Comercial B2C', 'B2C', 'Conversao direta em varejo e servicos ao consumidor', 'semanal', [
                'Abertura do ano e renovacao de ofertas',
                'Campanhas de relacionamento e prova social',
                'Educacao de produto com foco em dor/solucao',
                'Acoes sazonais e funil de meio',
                'Aceleracao de leads e retargeting',
                'Campanha de inverno/festas regionais',
                'Conteudo de autoridade + oferta limitada',
                'Posicionamento de marca e comunidade',
                'Preparacao para alta temporada',
                'Vitrine de produtos e bundles',
                'Aquecimento Black Friday e picos de venda',
                'Fechamento anual e recompra',
            ]),
            $this->template('b2b-consultivo', 'Plano Comercial B2B', 'B2B', 'Pipeline consultivo, autoridade e decisao corporativa', 'quinzenal', [
                'Diagnostico e tendencias do setor',
                'Cases de sucesso e ROI',
                'Frameworks e metodologia proprietaria',
                'Webinars e geracao de MQL',
                'Comparativos tecnicos e objeções',
                'Conteudo para comite de compra',
                'Materiais ricos e provas operacionais',
                'Parcerias estrategicas e co-marketing',
                'Roadmap de implementacao',
                'Auditoria e otimização de funil',
                'Proposta de valor para fechamento Q4',
                'Renovacao e expansao de contas',
            ]),
            $this->template('direto', 'Plano Direto de Vendas', 'Direto', 'Oferta clara, CTA forte e conversao imediata', 'diario', [
                'Oferta de entrada',
                'Beneficio principal',
                'Quebra de objeções',
                'Prova social em massa',
                'Comparativo de solucao',
                'Urgencia e escassez',
                'Campanha de meio de ano',
                'Reposicionamento da oferta',
                'Empilhamento de valor',
                'Teste de criativos de conversao',
                'Sprint promocional',
                'Recuperacao de carrinho e fidelizacao',
            ]),
            $this->template('indireto', 'Plano Indireto de Vendas', 'Indireto', 'Educacao e nutricao para venda por relacionamento', 'semanal', [
                'Conteudo de consciencia de problema',
                'Educacao de categoria',
                'Autoridade do especialista',
                'Historias de cliente',
                'Conteudo de bastidores',
                'Conteudo cultural e institucional',
                'Conteudo colaborativo',
                'Conteudo de comunidade',
                'Nutricao com mini-series',
                'Ponte para oferta',
                'Aquecimento de fechamento',
                'Retencao e comunidade ativa',
            ]),
            $this->template('aquecimento-vendas', 'Aquecimento de Vendas', 'Aquecimento', 'Preparacao da audiencia para ciclos comerciais intensos', 'semanal', [
                'Posicionamento e promessa central',
                'Dor latente e contexto de mercado',
                'Metodologia e pilares',
                'Prova e resultados',
                'Mini-ofertas de baixo risco',
                'Lista de espera e intencao',
                'Anticipacao de lancamento',
                'Conteudo de bastidores do produto',
                'Abertura de pré-venda',
                'Escalada de urgencia',
                'Janela de fechamento',
                'Entrega de valor pós-venda',
            ]),
            $this->template('marketing-clientes', 'Marketing para Clientes', 'Servicos para clientes', 'Plano versatil para agencias e consultorias', 'quinzenal', [
                'Imersao e diagnostico',
                'Planejamento editorial por nicho',
                'Implementacao e setup de canais',
                'Ajustes criativos por desempenho',
                'Ativacao de campanhas',
                'Otimização de funil',
                'Escala de audiencia',
                'Fortalecimento institucional',
                'Acoes de conversao',
                'Campanhas sazonais',
                'Expansao de ticket',
                'Renovacao e relatorio anual',
            ]),
            $this->template('artistas', 'Plano para Artistas', 'Arte', 'Presenca autoral, comunidade e monetizacao', 'semanal', [
                'Manifesto artistico do ano',
                'Processo criativo e bastidores',
                'Colecoes e obras em destaque',
                'Storytelling da jornada',
                'Engajamento de comunidade',
                'Parcerias e collabs',
                'Eventos, exposicoes e agenda',
                'Conteudo multimidia',
                'Aquecimento de lancamento',
                'Drop de produtos/obras',
                'Campanha de fim de ano',
                'Retrospectiva e novas fases',
            ]),
            $this->template('musicos', 'Plano para Músicos', 'Música', 'Engajamento, lancamentos e agenda de shows', 'semanal', [
                'Narrativa do novo ciclo',
                'Ensaios e backstage',
                'Trechos e teasers',
                'História das canções',
                'Interacao com fãs',
                'Conteudo de performance',
                'Pré-save e aquecimento',
                'Lançamento oficial',
                'Conteudo pós-lançamento',
                'Agenda de shows',
                'Produtos e merchandising',
                'Compilado anual e roadmap',
            ]),
            $this->template('livros', 'Plano para Livros', 'Editorial', 'Planejamento para autores, editoras e lancamentos literarios', 'quinzenal', [
                'Tema central e posicionamento',
                'Trechos e personagens',
                'Bastidores de escrita',
                'Conteudo educativo do tema',
                'Parcerias com influenciadores',
                'Construção de lista de leitores',
                'Aquecimento de pré-venda',
                'Semana de lancamento',
                'Provas sociais e reviews',
                'Clube de leitura',
                'Campanha sazonal de vendas',
                'Long tail e reedição',
            ]),
            $this->template('infoprodutos', 'Plano para Infoprodutos', 'Infoprodutos', 'Lançamentos, perpétuo e esteira de ofertas digitais', 'semanal', [
                'Diagnostico de avatar e dor',
                'Conteudo de autoridade',
                'Conteudo de transformação',
                'Captação para lead magnet',
                'Nutrição e micro-conversões',
                'Pré-lançamento 1',
                'Pré-lançamento 2',
                'Lançamento e carrinho aberto',
                'Follow-up de fechamento',
                'Perpétuo e remarketing',
                'Upsell/cross-sell',
                'Escala com novos criativos',
            ]),
            $this->template('lancamento-infoproduto', 'Lançamento de Infoproduto', 'Lançamento digital', 'Plano anual com ciclos de pré-lançamento, lançamento e perpétuo', 'diario', [
                'Ajuste de avatar, promessa e mecanismo único',
                'Construção de audiência e conteúdo de aquecimento',
                'Captação de leads e isca digital principal',
                'Pré-lançamento (provas, autoridade e objeções)',
                'Lançamento 1 com carrinho aberto e fechamento',
                'Pós-lançamento e reciclagem de criativos',
                'Perpétuo com funil de webinar/evergreen',
                'Reengajamento da base e novos depoimentos',
                'Lançamento 2 com oferta revisada',
                'Escala de tráfego e parceria de afiliados',
                'Sprint promocional de alta conversão',
                'Retenção, upsell e planejamento do próximo ano',
            ]),
        ];
    }

    public function findTemplate(string $slug): ?array
    {
        foreach ($this->templates() as $template) {
            if ($template['slug'] === $slug) {
                return $template;
            }
        }

        return null;
    }

    public function generateItems(array $template, int $year, string $frequency): array
    {
        $freq = in_array($frequency, ['diario', 'semanal', 'quinzenal', 'mensal'], true)
            ? $frequency
            : $template['default_frequency'];

        $items = [];
        for ($month = 1; $month <= 12; $month++) {
            $theme = $template['monthly_strategy'][$month] ?? 'Execucao de estrategia mensal';
            foreach ($this->scheduleDates($year, $month, $freq) as $date) {
                $items[] = [
                    'planned_date' => $date,
                    'title' => '[' . strtoupper($template['segment']) . '] ' . $theme,
                    'description' => 'Template: ' . $template['name'] . ' | Frequencia: ' . $freq . ' | Estrategia do mes: ' . $theme,
                    'format_type' => $this->formatByFrequency($freq),
                    'status' => 'planned',
                ];
            }
        }

        return $items;
    }

    private function template(
        string $slug,
        string $name,
        string $segment,
        string $description,
        string $defaultFrequency,
        array $monthlyStrategy
    ): array {
        $normalized = [];
        for ($i = 1; $i <= 12; $i++) {
            $normalized[$i] = $monthlyStrategy[$i - 1] ?? 'Estrategia mensal';
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'segment' => $segment,
            'description' => $description,
            'default_frequency' => $defaultFrequency,
            'monthly_strategy' => $normalized,
        ];
    }

    private function scheduleDates(int $year, int $month, string $frequency): array
    {
        $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = (int) $firstDay->format('t');
        $dates = [];

        if ($frequency === 'diario') {
            for ($d = 1; $d <= $lastDay; $d++) {
                $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
            }
            return $dates;
        }

        if ($frequency === 'quinzenal') {
            $dates[] = sprintf('%04d-%02d-01', $year, $month);
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, min(15, $lastDay));
            return array_values(array_unique($dates));
        }

        if ($frequency === 'mensal') {
            $targetDay = min(10, $lastDay);
            return [sprintf('%04d-%02d-%02d', $year, $month, $targetDay)];
        }

        // semanal (padrao): segunda-feira de cada semana.
        $cursor = $firstDay;
        while ((int) $cursor->format('N') !== 1) {
            $cursor = $cursor->modify('+1 day');
        }
        while ((int) $cursor->format('m') === $month) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+7 days');
        }

        if ($dates === []) {
            $dates[] = $firstDay->format('Y-m-d');
        }

        return $dates;
    }

    private function formatByFrequency(string $frequency): string
    {
        return match ($frequency) {
            'diario' => 'postagem promocional',
            'semanal' => 'carrossel',
            'quinzenal' => 'artigo',
            default => 'postagem educativa',
        };
    }
}
