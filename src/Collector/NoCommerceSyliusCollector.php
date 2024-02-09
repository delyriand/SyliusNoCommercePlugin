<?php

/*
 * This file is part of Monsieur Biz' No Commerce plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusNoCommercePlugin\Collector;

use MonsieurBiz\SyliusNoCommercePlugin\Context\NoCurrencyContext;
use MonsieurBiz\SyliusNoCommercePlugin\Provider\FeaturesProviderInterface;
use Sylius\Bundle\CoreBundle\Application\Kernel;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Context\CurrencyNotFoundException;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

final class NoCommerceSyliusCollector extends DataCollector
{
    private ShopperContextInterface $shopperContext;

    private FeaturesProviderInterface $featuresProvider;

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function __construct(
        ShopperContextInterface $shopperContext,
        array $bundles,
        string $defaultLocaleCode,
        FeaturesProviderInterface $featuresProvider
    ) {
        $this->shopperContext = $shopperContext;
        $this->featuresProvider = $featuresProvider;
        $this->data = [
            'version' => Kernel::VERSION . ($this->featuresProvider->isNoCommerceEnabledForChannel() ? ' NoCommerce' : ''),
            'base_currency_code' => null,
            'currency_code' => null,
            'default_locale_code' => $defaultLocaleCode,
            'locale_code' => null,
            'extensions' => [],
        ];

        // If the NoCommerce Plugin is enabled we change some data
        if ($this->featuresProvider->isNoCommerceEnabledForChannel()) {
            $this->data['base_currency_code'] = NoCurrencyContext::NONE_CURRENCY_CODE;
            $this->data['currency_code'] = NoCurrencyContext::NONE_CURRENCY_CODE;
            $this->data['extensions']['MonsieurBizNoCommercePlugin'] = ['name' => 'NoCommerce', 'enabled' => true];
        }

        $this->data['extensions'] += [
            'SyliusAdminApiBundle' => ['name' => 'API', 'enabled' => false],
            'SyliusAdminBundle' => ['name' => 'Admin', 'enabled' => false],
            'SyliusShopBundle' => ['name' => 'Shop', 'enabled' => false],
        ];

        foreach (array_keys($this->data['extensions']) as $bundleName) {
            if (isset($bundles[$bundleName])) {
                $this->data['extensions'][$bundleName]['enabled'] = true;
            }
        }
    }

    public function getVersion(): string
    {
        return $this->data['version'];
    }

    public function getExtensions(): array
    {
        return $this->data['extensions'];
    }

    public function getCurrencyCode(): ?string
    {
        return $this->data['currency_code'];
    }

    public function getLocaleCode(): ?string
    {
        return $this->data['locale_code'];
    }

    public function getDefaultCurrencyCode(): ?string
    {
        return $this->data['base_currency_code'];
    }

    public function getDefaultLocaleCode(): ?string
    {
        return $this->data['default_locale_code'];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        try {
            /** @var ChannelInterface $channel */
            $channel = $this->shopperContext->getChannel();

            if (!$this->featuresProvider->isNoCommerceEnabledForChannel()) {
                $baseCurrency = $channel->getBaseCurrency();
                $this->data['base_currency_code'] = $baseCurrency ? $baseCurrency->getCode() : NoCurrencyContext::NONE_CURRENCY_CODE;
                $this->data['currency_code'] = $this->shopperContext->getCurrencyCode();
            }
        } catch (ChannelNotFoundException|CurrencyNotFoundException $e) {
        }

        try {
            $this->data['locale_code'] = $this->shopperContext->getLocaleCode();
        } catch (LocaleNotFoundException $e) {
        }
    }

    public function reset(): void
    {
        $this->data['base_currency_code'] = NoCurrencyContext::NONE_CURRENCY_CODE;
        $this->data['currency_code'] = NoCurrencyContext::NONE_CURRENCY_CODE;
        $this->data['locale_code'] = null;
    }

    public function getName(): string
    {
        return 'sylius_core';
    }
}
