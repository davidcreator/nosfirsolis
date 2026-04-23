<?php

namespace System\Library;

class SocialFormatStandardsService
{
    public function standards(): array
    {
        return [
            'instagram' => [
                'name' => 'Instagram',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Fotos ate 1080px de largura. Proporcao aceita entre 1.91:1 e 3:4 (largura:altura).',
                    'official_limits' => 'Se sair do intervalo, ocorre recorte automatico.',
                    'recommended_canvas' => '1080x1440',
                    'recommended_ratio' => '3:4',
                    'recommended_safe_area' => '8% topo / 8% laterais / 16% base',
                    'notes' => 'Template vertical prioriza ocupacao de tela e preserva legibilidade.',
                    'source_keys' => ['ig_photo_resolution'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carrossel no feed com ate 10 midias; orientacao escolhida na primeira midia vale para todas.',
                    'official_limits' => 'Nao misturar orientacoes no mesmo carrossel para evitar recorte agressivo.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 15% base',
                    'notes' => 'Padrao quadrado reduz risco de corte entre dispositivos.',
                    'source_keys' => ['ig_carousel_help'],
                    'inference' => true,
                ],
            ],
            'facebook' => [
                'name' => 'Facebook',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Sem grade unica oficial publicada para posts organicos em fonte aberta consultavel; foco em formatos compativeis.',
                    'official_limits' => 'Use formatos amplamente aceitos para evitar recorte imprevisivel no feed.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Padrao interno seguro para post estatico.',
                    'source_keys' => ['meta_carousel_format', 'vimeo_publish_social'],
                    'inference' => true,
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carousel Ads suportam ate 10 imagens ou videos, cada card com link proprio.',
                    'official_limits' => 'Manter consistencia visual entre cards para narrativa e performance.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Padrao interno seguro para uso em feed e reaproveitamento multi-canal.',
                    'source_keys' => ['meta_carousel_format'],
                    'inference' => true,
                ],
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Imagem unica: 1.91:1, 1:1 e 4:5. Minimos por formato e recomendacao vertical 4:5.',
                    'official_limits' => 'Arquivo ate 5MB em ads de imagem unica; imagens menores que 401px podem virar thumbnail.',
                    'recommended_canvas' => '1200x1500',
                    'recommended_ratio' => '4:5',
                    'recommended_safe_area' => '9% topo / 8% laterais / 14% base',
                    'notes' => 'Vertical 4:5 tende a ganhar destaque no feed mobile.',
                    'source_keys' => ['linkedin_single_image', 'linkedin_share_photos'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carousel Ads: 2 a 10 cards; recomendacao 1080x1080 (1:1); 10MB por card.',
                    'official_limits' => 'JPG/PNG/GIF nao animado; texto de card pode truncar.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 15% base',
                    'notes' => 'Use narrativa em sequencia com headlines curtas por card.',
                    'source_keys' => ['linkedin_carousel_ads'],
                ],
            ],
            'tiktok' => [
                'name' => 'TikTok',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Criativos verticais com 3:4 fortemente recomendado; tambem 9:16.',
                    'official_limits' => 'Coberturas/imagens em JPG/PNG; formatos de anuncio exigem especificacoes de placement.',
                    'recommended_canvas' => '1080x1440',
                    'recommended_ratio' => '3:4',
                    'recommended_safe_area' => '10% topo / 8% laterais / 18% base',
                    'notes' => '3:4 facilita reaproveitamento para multiplas superficies de anuncios.',
                    'source_keys' => ['tiktok_streaming_specs'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carousel Ads: min 2 imagens; padroes horizontal 1200x628, quadrado 640x640 e vertical 720x1280.',
                    'official_limits' => 'Standard Carousel aceita ate 35 imagens; VSA Carousel mostra 2 a 20.',
                    'recommended_canvas' => '720x1280',
                    'recommended_ratio' => '9:16',
                    'recommended_safe_area' => '12% topo / 8% laterais / 20% base',
                    'notes' => 'Vertical melhora presenca no contexto mobile-first.',
                    'source_keys' => ['tiktok_carousel_specs'],
                ],
            ],
            'x-twitter' => [
                'name' => 'X / Twitter',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Expanded ratios suportados: 4:5 e 2:3, alem de 1:1, 1.91:1, 16:9 e 9:16.',
                    'official_limits' => 'Image ads recomendam 1200x1200 (1:1) ou 1200x628 (1.91:1).',
                    'recommended_canvas' => '1200x1200',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 12% base',
                    'notes' => '1:1 e consistente no feed e facilita versoes para carrossel.',
                    'source_keys' => ['x_creative_specs'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carrossel suporta imagem e video; proporcao deve ser consistente entre cards.',
                    'official_limits' => 'Imagem recomendada: 800x418 (1.91:1) ou 800x800 (1:1).',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 14% base',
                    'notes' => 'Padrao 1:1 reduz risco em mixed media carousel.',
                    'source_keys' => ['x_creative_specs'],
                ],
            ],
            'pinterest' => [
                'name' => 'Pinterest',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Pins de imagem aceitam formatos comuns e recomendam atencao a safe zones.',
                    'official_limits' => 'Titulo ate 100 caracteres; texto em caixa ate 250; descricao ate 800.',
                    'recommended_canvas' => '1000x1500',
                    'recommended_ratio' => '2:3',
                    'recommended_safe_area' => 'Topo 270px / Esq 65px / Dir 195px / Base 790px',
                    'notes' => '2:3 e formato consolidado para pin estatico.',
                    'source_keys' => ['pinterest_pin_specs', 'pinterest_product_specs'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Carousel Pins (ads) com 2-5 imagens para vendas sem catalogo; 2-10 para catalogo.',
                    'official_limits' => 'Aspect ratio de cards: 1:1 ou 2:3; PNG/JPEG.',
                    'recommended_canvas' => '1000x1000',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 15% base',
                    'notes' => '1:1 simplifica consistencia visual entre cards.',
                    'source_keys' => ['pinterest_product_specs'],
                ],
            ],
            'threads' => [
                'name' => 'Threads',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Sem documento publico consolidado de dimensoes no conjunto de fontes consultadas.',
                    'official_limits' => 'Plataforma com comportamento proximo a Instagram para imagem estendida.',
                    'recommended_canvas' => '1080x1440',
                    'recommended_ratio' => '3:4',
                    'recommended_safe_area' => '8% topo / 8% laterais / 16% base',
                    'notes' => 'Preset inferido a partir de ecossistema Meta/Instagram para manter compatibilidade.',
                    'source_keys' => ['ig_photo_resolution'],
                    'inference' => true,
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Sem guia oficial consolidado para carrossel em fonte aberta consultada.',
                    'official_limits' => 'Use cards com proporcao unica para evitar corte entre clientes.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Preset interno para robustez operacional.',
                    'source_keys' => ['ig_photo_resolution'],
                    'inference' => true,
                ],
            ],
            'youtube' => [
                'name' => 'YouTube',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Community post de imagem: ate 10 imagens, ate 16MB, proporcao sugerida 1:1.',
                    'official_limits' => 'JPG, PNG, GIF ou WEBP.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Padrao 1:1 segue exibicao nativa no feed de comunidade.',
                    'source_keys' => ['youtube_community_post'],
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Posts de comunidade aceitam multipla imagem (ate 10), funcionando como experiencia em sequencia.',
                    'official_limits' => 'Mesmas regras de formato e peso de imagem do post de comunidade.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Use narrativa slide-a-slide com texto curto por card.',
                    'source_keys' => ['youtube_community_post'],
                    'inference' => true,
                ],
            ],
            'vimeo' => [
                'name' => 'Vimeo',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Plataforma focada em video; orientacao varia por fluxo (16:9, 9:16 e 1:1 no editor).',
                    'official_limits' => 'Thumb custom recomendada com mesmas dimensoes do video.',
                    'recommended_canvas' => '1920x1080',
                    'recommended_ratio' => '16:9',
                    'recommended_safe_area' => '8% topo / 8% laterais / 10% base',
                    'notes' => 'Para capa de video, mantenha mesma resolucao do arquivo publicado.',
                    'source_keys' => ['vimeo_publish_social', 'vimeo_thumbnail'],
                ],
                'carousel' => [
                    'supported' => false,
                    'official_rule' => 'Nao ha carrossel nativo de imagens como formato principal no Vimeo.',
                    'official_limits' => 'Use playlists/showcases ou sequencias de videos.',
                    'recommended_canvas' => '1920x1080',
                    'recommended_ratio' => '16:9',
                    'recommended_safe_area' => '8% topo / 8% laterais / 10% base',
                    'notes' => 'Substituir carrossel por serie de videos/capas padronizadas.',
                    'source_keys' => ['vimeo_publish_social'],
                    'inference' => true,
                ],
            ],
            'blog' => [
                'name' => 'Blog',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Canal proprio sem regra unica de rede social.',
                    'official_limits' => 'Padronizar para SEO social share e leitura em dispositivos diversos.',
                    'recommended_canvas' => '1200x628',
                    'recommended_ratio' => '1.91:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 12% base',
                    'notes' => 'Padrao interno para Open Graph e previews.',
                    'source_keys' => [],
                    'inference' => true,
                ],
                'carousel' => [
                    'supported' => true,
                    'official_rule' => 'Nao nativo no formato feed; usar bloco de galeria/slider no CMS.',
                    'official_limits' => 'Manter consistencia de proporcao entre cards.',
                    'recommended_canvas' => '1200x1200',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '10% topo / 8% laterais / 14% base',
                    'notes' => 'Padrao interno para blocos de galeria.',
                    'source_keys' => [],
                    'inference' => true,
                ],
            ],
            'podcast' => [
                'name' => 'Podcast',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Canal de audio; capa e assets visuais seguem padrao de distribuicao do host.',
                    'official_limits' => 'Manter arte quadrada para melhor compatibilidade em apps.',
                    'recommended_canvas' => '3000x3000',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 10% base',
                    'notes' => 'Padrao interno de alta resolucao para capa.',
                    'source_keys' => [],
                    'inference' => true,
                ],
                'carousel' => [
                    'supported' => false,
                    'official_rule' => 'Nao ha carrossel nativo padrao para distribuicao de podcast.',
                    'official_limits' => 'Use serie de artes por episodio ou campanha.',
                    'recommended_canvas' => '1080x1080',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 12% base',
                    'notes' => 'Preset para divulgacao em redes sociais do podcast.',
                    'source_keys' => [],
                    'inference' => true,
                ],
            ],
            'email-marketing' => [
                'name' => 'E-mail marketing',
                'post' => [
                    'supported' => true,
                    'official_rule' => 'Canal proprietario sem proporcao unica; depende do template da ferramenta de envio.',
                    'official_limits' => 'Priorizar largura desktop e escalonamento mobile.',
                    'recommended_canvas' => '1200x628',
                    'recommended_ratio' => '1.91:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 12% base',
                    'notes' => 'Padrao interno para hero image responsiva.',
                    'source_keys' => [],
                    'inference' => true,
                ],
                'carousel' => [
                    'supported' => false,
                    'official_rule' => 'Carrossel em email depende de fallback e suporte de cliente de email.',
                    'official_limits' => 'Recomendado substituir por cards empilhados.',
                    'recommended_canvas' => '600x600',
                    'recommended_ratio' => '1:1',
                    'recommended_safe_area' => '8% topo / 8% laterais / 12% base',
                    'notes' => 'Padrao interno para blocos modulares.',
                    'source_keys' => [],
                    'inference' => true,
                ],
            ],
        ];
    }

    public function presetFor(string $platformSlug, string $formatType): ?array
    {
        $platformSlug = strtolower(trim($platformSlug));
        $formatType = strtolower(trim($formatType));
        if (!in_array($formatType, ['post', 'carousel'], true)) {
            return null;
        }

        $all = $this->standards();
        if (!isset($all[$platformSlug][$formatType])) {
            return null;
        }

        $entry = $all[$platformSlug][$formatType];
        [$width, $height] = $this->canvasToDimensions((string) ($entry['recommended_canvas'] ?? '1080x1080'));

        return [
            'platform_slug' => $platformSlug,
            'platform_name' => $all[$platformSlug]['name'] ?? $platformSlug,
            'format_type' => $formatType,
            'supported' => (bool) ($entry['supported'] ?? false),
            'official_rule' => (string) ($entry['official_rule'] ?? ''),
            'official_limits' => (string) ($entry['official_limits'] ?? ''),
            'recommended_canvas' => (string) ($entry['recommended_canvas'] ?? ''),
            'recommended_ratio' => (string) ($entry['recommended_ratio'] ?? ''),
            'recommended_safe_area' => (string) ($entry['recommended_safe_area'] ?? ''),
            'notes' => (string) ($entry['notes'] ?? ''),
            'is_inference' => (bool) ($entry['inference'] ?? false),
            'source_keys' => (array) ($entry['source_keys'] ?? []),
            'width_px' => $width,
            'height_px' => $height,
        ];
    }

    public function matrixRows(): array
    {
        $rows = [];
        foreach ($this->standards() as $slug => $data) {
            $rows[] = [
                'slug' => $slug,
                'name' => $data['name'],
                'post_canvas' => (string) ($data['post']['recommended_canvas'] ?? '-'),
                'post_ratio' => (string) ($data['post']['recommended_ratio'] ?? '-'),
                'carousel_supported' => (bool) ($data['carousel']['supported'] ?? false),
                'carousel_canvas' => (string) ($data['carousel']['recommended_canvas'] ?? '-'),
                'carousel_ratio' => (string) ($data['carousel']['recommended_ratio'] ?? '-'),
                'key_rule' => (string) ($data['post']['official_rule'] ?? ''),
                'has_inference' => (bool) (($data['post']['inference'] ?? false) || ($data['carousel']['inference'] ?? false)),
            ];
        }

        return $rows;
    }

    public function sourceMap(): array
    {
        return [
            'ig_photo_resolution' => [
                'label' => 'Instagram photo resolution (Help Center)',
                'url' => 'https://www.facebook.com/help/1631821640426723/',
                'checked_at' => '2026-04-16',
            ],
            'ig_carousel_help' => [
                'label' => 'Instagram carousel posting flow (Help Center)',
                'url' => 'https://www.facebook.com/help/269314186824048/',
                'checked_at' => '2026-04-16',
            ],
            'meta_carousel_format' => [
                'label' => 'Meta carousel ads overview',
                'url' => 'https://www.facebook.com/business/ads/carousel-ad-format',
                'checked_at' => '2026-04-16',
            ],
            'linkedin_carousel_ads' => [
                'label' => 'LinkedIn Carousel Ads specs',
                'url' => 'https://www.linkedin.com/help/linkedin/answer/a427022',
                'checked_at' => '2026-04-16',
            ],
            'linkedin_single_image' => [
                'label' => 'LinkedIn single image ads specs',
                'url' => 'https://www.linkedin.com/help/linkedin/answer/a426534',
                'checked_at' => '2026-04-16',
            ],
            'linkedin_share_photos' => [
                'label' => 'LinkedIn multi-photo post limits',
                'url' => 'https://www.linkedin.com/help/billing/answer/a527229',
                'checked_at' => '2026-04-16',
            ],
            'tiktok_carousel_specs' => [
                'label' => 'TikTok Carousel Ads specs',
                'url' => 'https://ads.tiktok.com/help/article/specifications-for-carousel-ads',
                'checked_at' => '2026-04-16',
            ],
            'tiktok_streaming_specs' => [
                'label' => 'TikTok Streaming Ads creative specs',
                'url' => 'https://ads.tiktok.com/help/article/creative-specifications-for-streaming-ads',
                'checked_at' => '2026-04-16',
            ],
            'x_creative_specs' => [
                'label' => 'X Ads creative specs',
                'url' => 'https://business.x.com/en/help/campaign-setup/creative-ad-specifications',
                'checked_at' => '2026-04-16',
            ],
            'pinterest_product_specs' => [
                'label' => 'Pinterest ad product specs',
                'url' => 'https://help.pinterest.com/de/business/article/pinterest-product-specs',
                'checked_at' => '2026-04-16',
            ],
            'pinterest_pin_specs' => [
                'label' => 'Pinterest pin specs',
                'url' => 'https://help.pinterest.com/es/article/review-pin-specs',
                'checked_at' => '2026-04-16',
            ],
            'youtube_community_post' => [
                'label' => 'YouTube Community post image specs',
                'url' => 'https://support.google.com/youtube/answer/7124474',
                'checked_at' => '2026-04-16',
            ],
            'youtube_ads_specs' => [
                'label' => 'YouTube / Google Ads video specs',
                'url' => 'https://support.google.com/google-ads/answer/13547298',
                'checked_at' => '2026-04-16',
            ],
            'vimeo_publish_social' => [
                'label' => 'Vimeo publish to social requirements',
                'url' => 'https://help.vimeo.com/hc/en-us/articles/12427445401361-About-restrictions-and-requirements-for-publishing-to-social',
                'checked_at' => '2026-04-16',
            ],
            'vimeo_thumbnail' => [
                'label' => 'Vimeo thumbnail recommendations',
                'url' => 'https://help.vimeo.com/hc/en-us/articles/12426471350289-How-to-change-the-thumbnail-image-for-my-video',
                'checked_at' => '2026-04-16',
            ],
        ];
    }

    public function resolveSources(array $keys): array
    {
        $map = $this->sourceMap();
        $resolved = [];
        foreach ($keys as $key) {
            if (isset($map[$key])) {
                $resolved[$key] = $map[$key];
            }
        }

        return $resolved;
    }

    private function canvasToDimensions(string $canvas): array
    {
        if (preg_match('/^\s*(\d{2,5})x(\d{2,5})\s*$/i', $canvas, $matches) !== 1) {
            return [1080, 1080];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}

