<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\amazonses\mail;

use AsyncAws\Ses\SesClient;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * @property-read null|string $settingsHtml
 */
class AmazonSesAdapter extends BaseTransportAdapter
{
    // Available SES regions should be listed in same order as in the docs:
    // https://docs.aws.amazon.com/general/latest/gr/ses.html
    public const REGIONS = [
        'us-east-2',
        'us-east-1',
        'us-west-1',
        'us-west-2',
        'ap-south-1',
        'ap-northeast-2',
        'ap-southeast-1',
        'ap-southeast-2',
        'ap-northeast-1',
        'ca-central-1',
        'eu-central-1',
        'eu-west-1',
        'eu-west-2',
        'eu-west-3',
        'eu-north-1',
        'sa-east-1',
        'us-gov-west-1'
    ];

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Amazon SES';
    }

    /**
     * @var string The AWS region to use
     */
    public string $region;

    /**
     * @var string The API key
     */
    public string $apiKey;

    /**
     * @var string The API secret
     */
    public string $apiSecret;

    /**
     * @var string Configuration set
     */
    public string $configurationSet = '';

    /**
     * @var string The SES API version to use
     */
    private string $_version = 'latest';

    /**
     * @var bool Debug mode
     */
    private bool $_debug = false;

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => Craft::t('amazon-ses', 'API Key'),
            'apiSecret' => Craft::t('amazon-ses', 'API Secret'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['region', 'apiKey', 'apiSecret', 'configurationSet'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['region'], 'required'],
            [['region'], 'in', 'range' => self::REGIONS, 'message' => Craft::t('amazon-ses',
                'The region provided is not a valid AWS region.'
            )],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('amazon-ses/_settings', [
            'adapter' => $this,
            'regions' => self::REGIONS,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function defineTransport(): array|AbstractTransport
    {
        $config = [
            'version' => $this->_version,
            'debug' => $this->_debug,
            'region' => App::parseEnv($this->region),
        ];

        $apiKey = App::parseEnv($this->apiKey);
        $apiSecret = App::parseEnv($this->apiSecret);

        // Only add the key and secret if they are found, otherwise use the default credential provider chain.
        // https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html
        if ($apiKey && $apiSecret) {
            $config['credentials'] = [
                'key' => $apiKey,
                'secret' => $apiSecret,
            ];
        }

        // Create new client
        $client = new SesClient($config);

        return new AmazonSesTransport($client, App::parseEnv($this->configurationSet));
    }
}
