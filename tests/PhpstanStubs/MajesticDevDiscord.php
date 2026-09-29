<?php

/**
 * PHPStan-only stubs for the private MajesticDev\Discord plugin (commandnet-discord-plugin), which
 * CI cannot install. Signatures mirror the real classes; nothing here is loaded at runtime.
 */

namespace MajesticDev\Discord\Api\DTO {
    class DiscordCommandOption
    {
        public readonly string $type;
        public string $name = '';
        public string $description = '';
        public bool $required = false;

        public function __construct(string $type = 'string')
        {
        }

        public function setName(string $name): static
        {
        }

        public function setDescription(string $description): static
        {
        }

        public function setRequired(bool $required = true): static
        {
        }
    }

    class DiscordCommandResult
    {
        public ?string $content = null;

        /** @var list<DiscordEmbed> */
        public array $embeds = [];
    }

    class DiscordEmbed
    {
        /** @var array{url: string}|null */
        public ?array $thumbnail = null;

        /** @var array{url: string}|null */
        public ?array $image = null;

        /** @var array{text: string}|null */
        public ?array $footer = null;

        /** @var list<array{name: string, value: string, inline?: bool}>|null */
        public ?array $fields = null;

        /** @var array{name: string, icon_url: string, url: string}|null */
        public ?array $author = null;

        public function __construct(
            public ?string $title = null,
            public ?string $description = null,
            public ?string $url = null,
        ) {
        }

        public function setThumbnail(string $thumbnail): static
        {
        }

        public function setImage(string $image): static
        {
        }

        public function addField(string $name = '', string $value = '', bool $inline = false): static
        {
        }

        public function setFooter(string $text): static
        {
        }
    }
}

namespace MajesticDev\Discord\Api\Resource {
    class DiscordCommandRun
    {
        public string $name;

        /** @var array<string, mixed> */
        public array $options;

        public string $discordUserId;

        public ?string $guildId = null;
    }
}

namespace MajesticDev\Discord\Discord {
    use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
    use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
    use MajesticDev\Discord\Api\Resource\DiscordCommandRun;

    interface DiscordCommandInterface
    {
        public function getName(): string;

        public function getDescription(): string;

        /**
         * @return list<DiscordCommandOption>
         */
        public function getOptions(): array;

        public function run(DiscordCommandRun $command): DiscordCommandResult;
    }
}
