<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class SetupCommand extends Command
{
    protected $signature = 'setup {--wizard : Run interactive setup wizard}';

    protected $description = 'Interactive setup guides for Knowledge CLI';

    public function handle(): int
    {
        if ($this->option('wizard')) {
            return $this->runWizard();
        }

        $this->showSetupOptions();

        return Command::SUCCESS;
    }

    private function showSetupOptions(): void
    {
        info('🚀 Knowledge CLI Setup Guides');
        info('');

        info('Choose your setup scenario:');
        info('');

        info('1. 🏠  Local Development (Solo Developer)');
        info('   ./know setup --wizard');
        info('');

        info('2. 👥  Team/Shared Knowledge Base');
        info('   See docs/SETUP_GUIDES.md#team-setup');
        info('');

        info('3. 🤖  AI-Enhanced Setup');
        info('   See docs/SETUP_GUIDES.md#ai-enhanced-setup');
        info('');

        info('4. 🐳  Docker Development');
        info('   See docs/SETUP_GUIDES.md#docker-development-setup');
        info('');

        info('5. 🏭  Production Deployment');
        info('   See docs/SETUP_GUIDES.md#production-setup');
        info('');

        info('📚 Complete documentation: docs/SETUP_GUIDES.md');
    }

    private function runWizard(): int
    {
        info('🧙‍♂️  Knowledge CLI Setup Wizard');
        info('');

        $scenario = select(
            label: 'What type of setup do you need?',
            options: [
                'local' => '🏠 Local Development (Solo Developer)',
                'team' => '👥 Team/Shared Knowledge Base',
                'ai' => '🤖 AI-Enhanced Setup',
                'docker' => '🐳 Docker Development Environment',
                'production' => '🏭 Production Deployment',
            ]
        );

        return match ($scenario) {
            'local' => $this->setupLocal(),
            'team' => $this->setupTeam(),
            'ai' => $this->setupAI(),
            'docker' => $this->setupDocker(),
            'production' => $this->setupProduction(),
            default => $this->showSetupOptions()
        };
    }

    private function setupLocal(): int
    {
        info('🏠 Setting up Local Development');
        info('');

        // Check if services are running
        if (confirm('Start Qdrant and Embedding services now?', default: true)) {
            spin(
                message: 'Starting services...',
                callback: fn () => $this->startServices()
            );
        }

        // Initialize collection
        if (confirm('Initialize Knowledge CLI now?', default: true)) {
            spin(
                message: 'Initializing...',
                callback: fn () => $this->initializeKnowledge()
            );
        }

        // Add first entry
        if (confirm('Add your first knowledge entry?', default: true)) {
            $this->addFirstEntry();
        }

        info('');
        info('✅ Local setup complete!');
        info('');
        info('Next steps:');
        info('  ./know search "your query"     # Search knowledge');
        info('  ./know add "new discovery"  # Add knowledge');
        info('  ./know stats                # View statistics');

        return Command::SUCCESS;
    }

    private function setupTeam(): int
    {
        info('👥 Team/Shared Setup Guide');
        info('');

        info('For team setup, you need:');
        info('');
        info('1. 🖥️  Central Server');
        info('   - Dedicated machine or cloud instance');
        info('   - Static IP recommended');
        info('   - See: docs/SETUP_GUIDES.md#team-setup');
        info('');

        info('2. 🔧  Configuration');
        info('   Server: BIND_ADDR=your-server-ip');
        info('   Clients: QDRANT_HOST=server-ip');
        info('   See: docs/SETUP_GUIDES.md#team-setup');
        info('');

        info('3. 🚀  Deployment');
        info('   docker compose -f docker-compose.remote.yml up -d');
        info('');

        if (confirm('Open team setup documentation?')) {
            $this->openDocumentation('team-setup');
        }

        return Command::SUCCESS;
    }

    private function setupAI(): int
    {
        info('🤖 AI-Enhanced Setup');
        info('');

        info('1. Install Ollama:');
        info('   curl -fsSL https://ollama.ai/install.sh | sh');
        info('');

        $model = select(
            label: 'Choose AI model:',
            options: [
                'llama2' => 'Llama 2 (Recommended)',
                'codellama' => 'Code Llama (Code-focused)',
                'mistral' => 'Mistral (Balanced)',
                'custom' => 'Custom model',
            ],
            default: 'llama2'
        );

        if ($model !== 'custom') {
            info("2. Pull model: ollama pull {$model}");
        }

        info('3. Configure environment:');
        info('   OLLAMA_HOST=http://localhost:11434');
        info("   OLLAMA_MODEL={$model}");
        info('');

        info('4. Enable features:');
        info('   ./know enhance:worker    # Process AI queue');
        info('   ./know insights          # AI-generated insights');
        info('   ./know synthesize       # Daily synthesis');
        info('');

        if (confirm('Test Ollama connection now?', default: false)) {
            $this->testOllama();
        }

        return Command::SUCCESS;
    }

    private function setupDocker(): int
    {
        info('🐳 Docker Development Setup');
        info('');

        $type = select(
            label: 'Docker setup type:',
            options: [
                'basic' => 'Basic Docker Compose',
                'dev-container' => 'Development Container',
                'production' => 'Production-ready',
            ]
        );

        match ($type) {
            'basic' => $this->showBasicDocker(),
            'dev-container' => $this->showDevContainer(),
            'production' => $this->showProductionDocker()
        };

        return Command::SUCCESS;
    }

    private function setupProduction(): int
    {
        info('🏭 Production Deployment');
        info('');

        info('Production setup requires:');
        info('');
        info('1. 🔧  Environment Configuration');
        info('   - Production .env with security settings');
        info('   - Resource limits and monitoring');
        info('');

        info('2. 🐳  Docker Compose Production');
        info('   docker compose -f docker-compose.prod.yml up -d');
        info('');

        info('3. 🔒  Security Considerations');
        info('   - Firewall configuration');
        info('   - SSL/TLS termination');
        info('   - Authentication setup');
        info('');

        info('4. 📊  Monitoring');
        info('   ./know agent:status      # Health checks');
        info('   ./know stats            # Usage metrics');
        info('   ./know maintain         # Regular maintenance');
        info('');

        warning('⚠️  See docs/SETUP_GUIDES.md#production-setup for complete guide');

        return Command::SUCCESS;
    }

    private function startServices(): void
    {
        $process = new \Symfony\Component\Process\Process(['make', 'up']);
        $process->run();

        if (! $process->isSuccessful()) {
            warning('Failed to start services. Try: docker compose up -d');

            return;
        }

        // Wait for services to be ready
        sleep(3);
    }

    private function initializeKnowledge(): void
    {
        $process = new \Symfony\Component\Process\Process(['./know', 'install']);
        $process->run();
    }

    private function addFirstEntry(): void
    {
        $title = text(
            label: 'Entry title:',
            required: true,
            default: 'Knowledge CLI Setup Complete'
        );

        $content = text(
            label: 'Entry content:',
            required: true,
            default: 'Successfully set up Knowledge CLI with Qdrant vector database and semantic search.'
        );

        $tagsInput = text(
            label: 'Tags (comma-separated):',
            default: 'setup,configuration,knowledge'
        );

        $tags = array_map('trim', explode(',', $tagsInput));

        $process = new \Symfony\Component\Process\Process([
            './know', 'add', $title,
            '--content='.$content,
            '--tags='.implode(',', $tags),
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            info('✅ First knowledge entry added!');
        } else {
            warning('Failed to add entry. Add manually with ./know add');
        }
    }

    private function testOllama(): void
    {
        $process = new \Symfony\Component\Process\Process(['curl', '-sf', 'http://localhost:11434/api/tags']);
        $process->run();

        if ($process->isSuccessful()) {
            info('✅ Ollama is running and accessible!');
        } else {
            warning('⚠️  Ollama not accessible. Check installation and service.');
        }
    }

    private function openDocumentation(string $section): void
    {
        $docPath = "docs/SETUP_GUIDES.md#{$section}";
        info("📚 Open: {$docPath}");
    }

    private function showBasicDocker(): void
    {
        info('Basic Docker Compose Setup:');
        info('');
        info('1. Create docker-compose.yml:');
        info('   (Use the one in project root)');
        info('');
        info('2. Start services:');
        info('   docker compose up -d');
        info('');
        info('3. Configure Knowledge CLI:');
        info('   QDRANT_HOST=localhost');
        info('   EMBEDDING_SERVER_URL=http://localhost:8001');
    }

    private function showDevContainer(): void
    {
        info('Development Container Setup:');
        info('');
        info('1. Create Dockerfile.dev (see docs)');
        info('2. Create docker-compose.dev.yml (see docs)');
        info('3. Start development environment:');
        info('   docker compose -f docker-compose.dev.yml up -d');
        info('4. Enter container:');
        info('   docker compose -f docker-compose.dev.yml exec knowledge-dev bash');
    }

    private function showProductionDocker(): void
    {
        info('Production Docker Setup:');
        info('');
        info('1. Use docker-compose.prod.yml');
        info('2. Configure production environment');
        info('3. Deploy:');
        info('   docker compose -f docker-compose.prod.yml up -d');
        info('4. Monitor:');
        info('   ./know agent:status');
    }
}
