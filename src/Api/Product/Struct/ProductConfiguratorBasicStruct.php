<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\Api\Entity\Entity;

class ProductConfiguratorBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $optionId;

    /**
     * @var array|null
     */
    protected $price;

    /**
     * @var array|null
     */
    protected $prices;

    /**
     * @var ConfigurationGroupOptionBasicStruct
     */
    protected $option;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function setOptionId(string $optionId): void
    {
        $this->optionId = $optionId;
    }

    public function getPrice(): ?array
    {
        return $this->price;
    }

    public function setPrice(?array $price): void
    {
        $this->price = $price;
    }

    public function getPrices(): ?array
    {
        return $this->prices;
    }

    public function setPrices(?array $prices): void
    {
        $this->prices = $prices;
    }

    public function getOption(): ConfigurationGroupOptionBasicStruct
    {
        return $this->option;
    }

    public function setOption(ConfigurationGroupOptionBasicStruct $option): void
    {
        $this->option = $option;
    }
}