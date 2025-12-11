<?php

declare(strict_types=1);

namespace App\Connector;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;

/**
 * Connector for static site generators via Git (Hugo, Jekyll, Gatsby, etc.)
 * Creates markdown files and commits to a Git repository
 */
class GitConnector extends AbstractSiteConnector
{
    public static function getType(): string
    {
        return 'git';
    }

    public static function getDisplayName(): string
    {
        return 'Git / Sites Statiques';
    }

    public static function getDescription(): string
    {
        return 'Publiez sur des sites statiques (Hugo, Jekyll, Gatsby, Astro) via Git. Les articles sont crees en Markdown et commites automatiquement.';
    }

    public static function getIcon(): string
    {
        return 'git';
    }

    public static function getConfigurationFields(): array
    {
        return [
            'url' => [
                'label' => 'URL du repository',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://github.com/user/repo',
                'help' => 'URL du repository Git (GitHub, GitLab, Bitbucket)',
            ],
            'apiToken' => [
                'label' => 'Token d\'acces',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'ghp_xxxxxxxxxxxx',
                'help' => 'Personal Access Token avec droits d\'ecriture sur le repo',
            ],
            'provider' => [
                'label' => 'Provider Git',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'github' => 'GitHub',
                    'gitlab' => 'GitLab',
                    'bitbucket' => 'Bitbucket',
                ],
                'help' => 'Plateforme hebergeant votre repository',
            ],
            'branch' => [
                'label' => 'Branche',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'main',
                'help' => 'Branche sur laquelle publier (defaut: main)',
            ],
            'contentPath' => [
                'label' => 'Chemin du contenu',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'content/posts',
                'help' => 'Dossier ou creer les fichiers markdown',
            ],
            'siteGenerator' => [
                'label' => 'Generateur de site',
                'type' => 'select',
                'required' => false,
                'options' => [
                    'hugo' => 'Hugo',
                    'jekyll' => 'Jekyll',
                    'gatsby' => 'Gatsby',
                    'astro' => 'Astro',
                    'eleventy' => 'Eleventy (11ty)',
                    'nextjs' => 'Next.js',
                    'other' => 'Autre',
                ],
                'help' => 'Type de generateur pour adapter le format du frontmatter',
            ],
            'siteUrl' => [
                'label' => 'URL du site publie',
                'type' => 'url',
                'required' => false,
                'placeholder' => 'https://monblog.com',
                'help' => 'URL du site une fois deploye (pour les liens)',
            ],
        ];
    }

    protected function getSupportedFeatures(): array
    {
        return [
            self::FEATURE_PUBLISH,
            self::FEATURE_CATEGORIES,
            self::FEATURE_TAGS,
            self::FEATURE_DRAFT,
        ];
    }

    public function testConnection(Site $site): bool
    {
        try {
            $config = $site->getConfig();
            $provider = $config['provider'] ?? 'github';

            // Test API access by fetching repo info
            $repoInfo = $this->getRepoInfo($site);
            return !empty($repoInfo);
        } catch (\Exception $e) {
            $this->logger->warning('Git connection test failed', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function publishArticle(Site $site, ArticleDTO $article): array
    {
        $config = $site->getConfig();
        $provider = $config['provider'] ?? 'github';

        $this->logger->info('Publishing article via Git', [
            'site' => $site->getName(),
            'provider' => $provider,
            'title' => $article->title,
        ]);

        try {
            // Generate markdown content with frontmatter
            $markdownContent = $this->generateMarkdown($article, $config);
            $filename = $this->generateFilename($article, $config);
            $contentPath = $config['contentPath'] ?? 'content/posts';
            $filePath = trim($contentPath, '/') . '/' . $filename;
            $branch = $config['branch'] ?? 'main';

            // Create or update file via Git provider API
            $result = match ($provider) {
                'github' => $this->createGitHubFile($site, $filePath, $markdownContent, $branch, $article->title),
                'gitlab' => $this->createGitLabFile($site, $filePath, $markdownContent, $branch, $article->title),
                default => throw new \RuntimeException('Provider non supporte: ' . $provider),
            };

            $siteUrl = $config['siteUrl'] ?? null;
            $articleUrl = $siteUrl ? $this->generateArticleUrl($article, $config) : null;

            return [
                'success' => true,
                'id' => $result['sha'] ?? $result['id'] ?? null,
                'url' => $articleUrl,
                'message' => 'Article commite avec succes',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish via Git', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function fetchStats(Site $site): StatsDTO
    {
        $config = $site->getConfig();

        try {
            $repoInfo = $this->getRepoInfo($site);
            $contentPath = $config['contentPath'] ?? 'content/posts';
            $files = $this->listFiles($site, $contentPath);
            $markdownFiles = array_filter($files, fn($f) => str_ends_with($f['name'] ?? '', '.md'));

            return new StatsDTO(
                totalPosts: count($markdownFiles),
                totalCategories: 0,
                totalTags: 0,
                siteTitle: $repoInfo['name'] ?? $site->getName(),
                wordpressVersion: null,
                fetchedAt: new \DateTimeImmutable()
            );
        } catch (\Exception $e) {
            return new StatsDTO(
                totalPosts: 0,
                totalCategories: 0,
                totalTags: 0,
                siteTitle: $site->getName(),
                fetchedAt: new \DateTimeImmutable()
            );
        }
    }

    /**
     * Generate markdown content with frontmatter
     */
    private function generateMarkdown(ArticleDTO $article, array $config): string
    {
        $generator = $config['siteGenerator'] ?? 'hugo';
        $frontmatter = $this->generateFrontmatter($article, $generator);

        return "---\n" . $frontmatter . "---\n\n" . $article->content;
    }

    /**
     * Generate frontmatter based on site generator type
     */
    private function generateFrontmatter(ArticleDTO $article, string $generator): string
    {
        $date = (new \DateTimeImmutable())->format('Y-m-d\TH:i:sP');
        $draft = $article->status === 'draft' ? 'true' : 'false';

        $frontmatter = match ($generator) {
            'hugo' => [
                'title' => $article->title,
                'date' => $date,
                'draft' => $draft,
                'description' => $article->excerpt ?? '',
                'categories' => $article->categories ?? [],
                'tags' => $article->tags ?? [],
            ],
            'jekyll' => [
                'layout' => 'post',
                'title' => $article->title,
                'date' => $date,
                'categories' => implode(' ', $article->categories ?? []),
                'tags' => $article->tags ?? [],
                'excerpt' => $article->excerpt ?? '',
            ],
            'gatsby', 'nextjs' => [
                'title' => $article->title,
                'date' => $date,
                'published' => $article->status !== 'draft',
                'description' => $article->excerpt ?? '',
                'tags' => $article->tags ?? [],
            ],
            'astro' => [
                'title' => $article->title,
                'pubDate' => $date,
                'draft' => $article->status === 'draft',
                'description' => $article->excerpt ?? '',
                'tags' => $article->tags ?? [],
            ],
            default => [
                'title' => $article->title,
                'date' => $date,
                'draft' => $draft,
            ],
        };

        return $this->arrayToYaml($frontmatter);
    }

    /**
     * Convert array to YAML format
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $yaml .= "{$prefix}{$key}: []\n";
                } elseif (array_keys($value) === range(0, count($value) - 1)) {
                    // Sequential array
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        $yaml .= "{$prefix}  - " . (is_string($item) ? "\"{$item}\"" : $item) . "\n";
                    }
                } else {
                    // Associative array
                    $yaml .= "{$prefix}{$key}:\n" . $this->arrayToYaml($value, $indent + 1);
                }
            } elseif (is_bool($value)) {
                $yaml .= "{$prefix}{$key}: " . ($value ? 'true' : 'false') . "\n";
            } elseif (is_string($value)) {
                // Escape quotes in strings
                $escaped = str_replace('"', '\\"', $value);
                $yaml .= "{$prefix}{$key}: \"{$escaped}\"\n";
            } else {
                $yaml .= "{$prefix}{$key}: {$value}\n";
            }
        }

        return $yaml;
    }

    /**
     * Generate filename from article title
     */
    private function generateFilename(ArticleDTO $article, array $config): string
    {
        $generator = $config['siteGenerator'] ?? 'hugo';
        $slug = $this->slugify($article->title);
        $date = (new \DateTimeImmutable())->format('Y-m-d');

        return match ($generator) {
            'jekyll' => "{$date}-{$slug}.md",
            default => "{$slug}.md",
        };
    }

    /**
     * Generate article URL based on config
     */
    private function generateArticleUrl(ArticleDTO $article, array $config): string
    {
        $siteUrl = rtrim($config['siteUrl'] ?? '', '/');
        $slug = $this->slugify($article->title);

        return "{$siteUrl}/posts/{$slug}/";
    }

    /**
     * Convert string to URL-friendly slug
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }

    /**
     * Get repository info from provider
     */
    private function getRepoInfo(Site $site): array
    {
        $config = $site->getConfig();
        $provider = $config['provider'] ?? 'github';
        $repoPath = $this->extractRepoPath($site->getUrl());

        return match ($provider) {
            'github' => $this->makeRequest('GET', "https://api.github.com/repos/{$repoPath}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $site->getApiToken(),
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]),
            'gitlab' => $this->makeRequest('GET', "https://gitlab.com/api/v4/projects/" . urlencode($repoPath), [
                'headers' => [
                    'PRIVATE-TOKEN' => $site->getApiToken(),
                ],
            ]),
            default => [],
        };
    }

    /**
     * List files in a directory
     */
    private function listFiles(Site $site, string $path): array
    {
        $config = $site->getConfig();
        $provider = $config['provider'] ?? 'github';
        $repoPath = $this->extractRepoPath($site->getUrl());
        $branch = $config['branch'] ?? 'main';

        try {
            return match ($provider) {
                'github' => $this->makeRequest('GET', "https://api.github.com/repos/{$repoPath}/contents/{$path}?ref={$branch}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $site->getApiToken(),
                        'Accept' => 'application/vnd.github.v3+json',
                    ],
                ]),
                'gitlab' => $this->makeRequest('GET', "https://gitlab.com/api/v4/projects/" . urlencode($repoPath) . "/repository/tree?path={$path}&ref={$branch}", [
                    'headers' => [
                        'PRIVATE-TOKEN' => $site->getApiToken(),
                    ],
                ]),
                default => [],
            };
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Create file on GitHub
     */
    private function createGitHubFile(Site $site, string $path, string $content, string $branch, string $commitMessage): array
    {
        $repoPath = $this->extractRepoPath($site->getUrl());

        return $this->makeRequest('PUT', "https://api.github.com/repos/{$repoPath}/contents/{$path}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $site->getApiToken(),
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'json' => [
                'message' => "Add: {$commitMessage}",
                'content' => base64_encode($content),
                'branch' => $branch,
            ],
        ]);
    }

    /**
     * Create file on GitLab
     */
    private function createGitLabFile(Site $site, string $path, string $content, string $branch, string $commitMessage): array
    {
        $repoPath = $this->extractRepoPath($site->getUrl());

        return $this->makeRequest('POST', "https://gitlab.com/api/v4/projects/" . urlencode($repoPath) . "/repository/files/" . urlencode($path), [
            'headers' => [
                'PRIVATE-TOKEN' => $site->getApiToken(),
            ],
            'json' => [
                'branch' => $branch,
                'content' => $content,
                'commit_message' => "Add: {$commitMessage}",
            ],
        ]);
    }

    /**
     * Extract repository path from URL
     */
    private function extractRepoPath(string $url): string
    {
        // Handle various URL formats
        $url = preg_replace('#^https?://(www\.)?#', '', $url);
        $url = preg_replace('#^(github\.com|gitlab\.com|bitbucket\.org)/#', '', $url);
        $url = preg_replace('#\.git$#', '', $url);
        return trim($url, '/');
    }

    public function fetchPosts(Site $site, array $filters = []): array
    {
        $config = $site->getConfig() ?? [];
        $contentPath = $config['contentPath'] ?? 'content/posts';
        $provider = $config['provider'] ?? 'github';

        try {
            $files = $this->listFiles($site, $contentPath);
            $markdownFiles = array_filter($files, fn($f) => str_ends_with($f['name'] ?? '', '.md'));

            $posts = [];
            foreach ($markdownFiles as $file) {
                $post = $this->fetchFileContent($site, $file, $config);
                if ($post) {
                    $posts[] = $post;
                }
            }

            // Sort by date descending
            usort($posts, fn($a, $b) => strtotime($b['date'] ?? '0') - strtotime($a['date'] ?? '0'));

            // Apply pagination
            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 10;
            $total = count($posts);
            $posts = array_slice($posts, ($page - 1) * $perPage, $perPage);

            return [
                'posts' => $posts,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch posts from Git', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'posts' => [],
                'total' => 0,
                'pages' => 0,
            ];
        }
    }

    public function fetchPost(Site $site, string|int $postId): ?array
    {
        $config = $site->getConfig() ?? [];
        $contentPath = $config['contentPath'] ?? 'content/posts';

        // postId is the filename (slug.md)
        $filePath = $contentPath . '/' . $postId;

        try {
            $content = $this->getFileContent($site, $filePath);
            if (!$content) {
                return null;
            }

            return $this->parseMarkdownFile($content, $postId, $config);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch post from Git', [
                'site' => $site->getName(),
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function fetchPages(Site $site, array $filters = []): array
    {
        // For static sites, we could look in a different folder
        // For now, return empty
        return [
            'pages' => [],
            'total' => 0,
            'pages_count' => 0,
        ];
    }

    /**
     * Fetch content of a file from Git
     */
    private function fetchFileContent(Site $site, array $file, array $config): ?array
    {
        $provider = $config['provider'] ?? 'github';
        $repoPath = $this->extractRepoPath($site->getUrl());
        $branch = $config['branch'] ?? 'main';

        try {
            if ($provider === 'github') {
                $response = $this->makeRequest('GET', $file['url'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $site->getApiToken(),
                        'Accept' => 'application/vnd.github.v3+json',
                    ],
                ]);

                $content = base64_decode($response['content'] ?? '');
            } else {
                // GitLab
                $filePath = urlencode($file['path']);
                $response = $this->makeRequest('GET', "https://gitlab.com/api/v4/projects/" . urlencode($repoPath) . "/repository/files/{$filePath}/raw?ref={$branch}", [
                    'headers' => [
                        'PRIVATE-TOKEN' => $site->getApiToken(),
                    ],
                ]);
                $content = is_string($response) ? $response : json_encode($response);
            }

            return $this->parseMarkdownFile($content, $file['name'], $config);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch file content', [
                'file' => $file['name'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get raw file content
     */
    private function getFileContent(Site $site, string $path): ?string
    {
        $config = $site->getConfig() ?? [];
        $provider = $config['provider'] ?? 'github';
        $repoPath = $this->extractRepoPath($site->getUrl());
        $branch = $config['branch'] ?? 'main';

        try {
            if ($provider === 'github') {
                $response = $this->makeRequest('GET', "https://api.github.com/repos/{$repoPath}/contents/{$path}?ref={$branch}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $site->getApiToken(),
                        'Accept' => 'application/vnd.github.v3+json',
                    ],
                ]);

                return base64_decode($response['content'] ?? '');
            } else {
                $filePath = urlencode($path);
                $response = $this->httpClient->request('GET', "https://gitlab.com/api/v4/projects/" . urlencode($repoPath) . "/repository/files/{$filePath}/raw?ref={$branch}", [
                    'headers' => [
                        'PRIVATE-TOKEN' => $site->getApiToken(),
                    ],
                ]);

                return $response->getContent();
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse a markdown file with frontmatter
     */
    private function parseMarkdownFile(string $content, string $filename, array $config): array
    {
        $frontmatter = [];
        $body = $content;

        // Extract frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatterRaw = $matches[1];
            $body = $matches[2];

            // Simple YAML parsing for common fields
            foreach (explode("\n", $frontmatterRaw) as $line) {
                if (preg_match('/^(\w+):\s*(.*)$/', trim($line), $m)) {
                    $key = $m[1];
                    $value = trim($m[2], '"\'');
                    $frontmatter[$key] = $value;
                }
            }
        }

        $slug = pathinfo($filename, PATHINFO_FILENAME);
        $siteUrl = $config['siteUrl'] ?? '';

        return [
            'id' => $filename,
            'title' => $frontmatter['title'] ?? $slug,
            'slug' => $slug,
            'excerpt' => $frontmatter['description'] ?? $frontmatter['excerpt'] ?? '',
            'content' => $body,
            'status' => ($frontmatter['draft'] ?? 'false') === 'true' ? 'draft' : 'publish',
            'url' => $siteUrl ? rtrim($siteUrl, '/') . '/posts/' . $slug . '/' : '',
            'date' => $frontmatter['date'] ?? $frontmatter['pubDate'] ?? null,
            'modified' => $frontmatter['lastmod'] ?? $frontmatter['modified'] ?? null,
            'categories' => isset($frontmatter['categories']) ? explode(',', $frontmatter['categories']) : [],
            'tags' => isset($frontmatter['tags']) ? explode(',', $frontmatter['tags']) : [],
            'type' => 'post',
        ];
    }
}
