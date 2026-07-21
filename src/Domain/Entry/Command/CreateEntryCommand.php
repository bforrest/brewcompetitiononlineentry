<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating a new entry. Validated by EntryValidationService before passed to EntryService.
 * All field names match legacy form POST parameters for minimal translation friction.
 * Not final: UpdateEntryCommand extends this to add an id field.
 */
class CreateEntryCommand
{
    #[Assert\NotBlank(message: 'Entry name is required')]
    #[Assert\Length(min: 1, max: 255, maxMessage: 'Entry name must not exceed 255 characters')]
    public string $brewName = '';

    #[Assert\NotBlank(message: 'Style is required')]
    public string $brewCategorySort = '';

    #[Assert\NotBlank(message: 'Subcategory is required')]
    public string $brewSubCategory = '';

    public ?string $brewInfo = null;

    public ?string $brewMead1 = null;

    public ?string $brewMead2 = null;

    public ?string $brewMead3 = null;

    #[Assert\Length(max: 500, maxMessage: 'Comments must not exceed 500 characters')]
    public ?string $brewComments = null;

    public ?string $brewCoBrewer = null;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'ABV must be a valid decimal number')]
    public ?string $brewABV = null;

    public ?string $brewInfoOptional = null;

    #[Assert\Length(max: 255, maxMessage: 'Allergen info must not exceed 255 characters')]
    public ?string $brewPossAllergens = null;

    public ?string $brewPouring = null;

    public ?string $brewJuiceSource = null;

    public ?string $brewSweetnessLevel = null;

    public ?string $brewPackaging = null;

    public ?string $regionalVar = null;

    public ?int $brewBrewerId = null;

    public ?string $brewBrewerFirstName = null;

    public ?string $brewBrewerLastName = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
