<?php

/**
 * Stand-ins for the private MajesticDev\Discord plugin (commandnet-discord-plugin), which CI cannot
 * install. Signatures and behaviour mirror the real classes (minus their API Platform/serializer
 * attributes). PHPStan reads this file (scanFiles) and tests/bootstrap.php loads it when the real
 * plugin is absent, so the Discord commands can be unit tested.
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
            $this->type = $type;
        }

        public function setName(string $name): static
        {
            $this->name = $name;
            return $this;
        }

        public function setDescription(string $description): static
        {
            $this->description = $description;
            return $this;
        }

        public function setRequired(bool $required = true): static
        {
            $this->required = $required;
            return $this;
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
            $this->thumbnail = ['url' => $thumbnail];
            return $this;
        }

        public function setImage(string $image): static
        {
            $this->image = ['url' => $image];
            return $this;
        }

        public function addField(string $name = '', string $value = '', bool $inline = false): static
        {
            $this->fields[] = ['name' => $name, 'value' => $value, 'inline' => $inline];
            return $this;
        }

        public function setFooter(string $text): static
        {
            $this->footer = ['text' => $text];
            return $this;
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
