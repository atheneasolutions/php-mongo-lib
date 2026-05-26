<?php

namespace Athenea\MongoLib\Tests\Model;

use Athenea\MongoLib\Attribute\BsonDiscriminator;
use Athenea\MongoLib\Attribute\BsonSerialize;
use Athenea\MongoLib\Model\Base;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;

enum TestPlatform: string
{
    case Web = 'web';
    case Mobile = 'mobile';
}

enum TestStatus: int
{
    case Pending = 0;
    case Active = 1;
}

class SimpleModel extends Base
{
    #[BsonSerialize]
    private ?string $name = null;
    #[BsonSerialize]
    private ?int $count = null;
    #[BsonSerialize]
    private ?bool $active = null;
    #[BsonSerialize]
    private ?float $ratio = null;

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
    public function getCount(): ?int { return $this->count; }
    public function setCount(?int $count): void { $this->count = $count; }
    public function isActive(): ?bool { return $this->active; }
    public function setActive(?bool $active): void { $this->active = $active; }
    public function getRatio(): ?float { return $this->ratio; }
    public function setRatio(?float $ratio): void { $this->ratio = $ratio; }
}

class ChildModel extends SimpleModel
{
    #[BsonSerialize]
    private ?string $extra = null;

    public function getExtra(): ?string { return $this->extra; }
    public function setExtra(?string $extra): void { $this->extra = $extra; }
}

class GrandchildModel extends ChildModel
{
    #[BsonSerialize]
    private ?string $deep = null;

    public function getDeep(): ?string { return $this->deep; }
    public function setDeep(?string $deep): void { $this->deep = $deep; }
}

class ExtendedSimpleModel extends SimpleModel
{
    #[BsonSerialize(name: 'renamed_extra')]
    private ?string $extra = null;

    #[BsonSerialize]
    private ?TestPlatform $platform = null;

    public function getExtra(): ?string { return $this->extra; }
    public function setExtra(?string $extra): void { $this->extra = $extra; }
    public function getPlatform(): ?TestPlatform { return $this->platform; }
    public function setPlatform(?TestPlatform $platform): void { $this->platform = $platform; }
}

abstract class AbstractAnimal extends Base
{
    #[BsonSerialize]
    private ?string $name = null;

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
}

#[BsonDiscriminator('type', ['cat' => CatModel::class, 'dog' => DogModel::class])]
class CatModel extends AbstractAnimal
{
    #[BsonSerialize]
    private ?int $lives = null;

    public function getLives(): ?int { return $this->lives; }
    public function setLives(?int $lives): void { $this->lives = $lives; }
}

class DogModel extends AbstractAnimal
{
    #[BsonSerialize]
    private ?string $breed = null;

    public function getBreed(): ?string { return $this->breed; }
    public function setBreed(?string $breed): void { $this->breed = $breed; }
}

class CustomNameModel extends Base
{
    #[BsonSerialize(name: '_id')]
    private ?ObjectId $id = null;
    #[BsonSerialize(name: 'custom_field_name')]
    private ?string $field = null;
    #[BsonSerialize]
    private ?\DateTime $createdAt = null;

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getField(): ?string { return $this->field; }
    public function setField(?string $field): void { $this->field = $field; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
}

class EnumModel extends Base
{
    #[BsonSerialize]
    private ?TestPlatform $platform = null;
    #[BsonSerialize]
    private ?TestStatus $status = null;

    public function getPlatform(): ?TestPlatform { return $this->platform; }
    public function setPlatform(?TestPlatform $platform): void { $this->platform = $platform; }
    public function getStatus(): ?TestStatus { return $this->status; }
    public function setStatus(?TestStatus $status): void { $this->status = $status; }
}

class NestedModel extends Base
{
    #[BsonSerialize]
    private ?Base $child = null;

    public function getChild(): ?Base { return $this->child; }
    public function setChild(?Base $child): void { $this->child = $child; }
}

class SimpleNestedModel extends Base
{
    #[BsonSerialize]
    private ?SimpleModel $child = null;

    public function getChild(): ?SimpleModel { return $this->child; }
    public function setChild(?SimpleModel $child): void { $this->child = $child; }
}

class ArrayModel extends Base
{
    /** @var SimpleModel[] */
    #[BsonSerialize]
    private array $items = [];

    #[BsonSerialize]
    private array $tags = [];

    public function getItems(): array { return $this->items; }
    public function setItems(array $items): void { $this->items = $items; }
    public function getTags(): array { return $this->tags; }
    public function setTags(array $tags): void { $this->tags = $tags; }
}

class DateTimeModel extends Base
{
    #[BsonSerialize]
    private ?\DateTime $timestamp = null;

    public function getTimestamp(): ?\DateTime { return $this->timestamp; }
    public function setTimestamp(?\DateTime $timestamp): void { $this->timestamp = $timestamp; }
}

class ObjectIdModel extends Base
{
    #[BsonSerialize(name: '_id')]
    private ?ObjectId $id = null;
    #[BsonSerialize]
    private ?ObjectId $refId = null;

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getRefId(): ?ObjectId { return $this->refId; }
    public function setRefId(?ObjectId $refId): void { $this->refId = $refId; }
}

class PublicPropertyModel extends Base
{
    #[BsonSerialize]
    public ?string $name = null;
    #[BsonSerialize]
    public ?int $value = null;
}

class MethodAttributeModel extends Base
{
    private ?string $origin = null;

    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $origin): void { $this->origin = $origin; }

    #[BsonSerialize]
    public function getBy(): ?string { return $this->origin; }

    #[BsonSerialize]
    public function setBy(?string $by): void { $this->origin = $by; }
}

class MixedValueModel extends Base
{
    #[BsonSerialize]
    private mixed $value = null;
    #[BsonSerialize]
    private mixed $previousValue = null;

    public function getValue(): mixed { return $this->value; }
    public function setValue(mixed $value): void { $this->value = $value; }
    public function getPreviousValue(): mixed { return $this->previousValue; }
    public function setPreviousValue(mixed $previousValue): void { $this->previousValue = $previousValue; }
}

class NoAttributeModel extends Base
{
    private ?string $name = null;

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
}

class VirtualFieldModel extends Base
{
    #[BsonSerialize]
    private ?string $name = null;

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    #[BsonSerialize]
    public function getComputed(): string { return 'always_this'; }
}

class NullableModel extends Base
{
    #[BsonSerialize]
    private ?string $name = null;
    #[BsonSerialize]
    private string $required = 'default';

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
    public function getRequired(): string { return $this->required; }
    public function setRequired(string $required): void { $this->required = $required; }
}

class StdClassModel extends Base
{
    #[BsonSerialize]
    private ?\stdClass $metadata = null;

    public function getMetadata(): ?\stdClass { return $this->metadata; }
    public function setMetadata(?\stdClass $metadata): void { $this->metadata = $metadata; }
}

// ========================================
// EMC-Backend Patterns
// ========================================

enum EmcUserType: string
{
    case Patient = 'patient';
    case Professional = 'profesional';
}

enum EmcUserState: string
{
    case Registered = 'registered';
    case InWaitingRoom = 'inWaitingRoom';
    case InMeeting = 'inMeeting';
    case OutMeeting = 'outMeeting';
}

enum EmcDelegationType: string
{
    case MinorSixteen = 'MINOR_SIXTEEN';
    case LegallyIncapacitated = 'LEGALLY_INCAPACITATED';
}

enum EmcRegStatus: string
{
    case PendingEmail = 'pending_email';
    case PendingActivation = 'pending_activation';
    case Validated = 'validated';
    case Refused = 'refused';
}

enum EmcStatType: string
{
    case Login = 'LOGIN';
    case Questionnaires = 'QUESTIONNAIRES';
    case ReportDownload = 'REPORT_DOWNLOAD';
}

abstract class EmcMongoBase extends Base
{
    #[BsonSerialize(name: '_id')]
    protected ?ObjectId $id = null;

    #[BsonSerialize]
    protected ?\DateTime $createdAt = null;

    #[BsonSerialize]
    protected ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): void { $this->updatedAt = $updatedAt; }
}

class EmcMongoBaseConcrete extends EmcMongoBase
{
}

class EmcSurvey extends Base
{
    #[BsonSerialize]
    private int $q1 = 1;

    #[BsonSerialize]
    private int $q2 = 1;

    #[BsonSerialize]
    private int $q3 = 1;

    #[BsonSerialize]
    private ?\DateTime $answered = null;

    public function getq1(): int { return $this->q1; }
    public function setq1(int $q1): void { $this->q1 = $q1; }
    public function getq2(): int { return $this->q2; }
    public function setq2(int $q2): void { $this->q2 = $q2; }
    public function getq3(): int { return $this->q3; }
    public function setq3(int $q3): void { $this->q3 = $q3; }
    public function getAnswered(): ?\DateTime { return $this->answered; }
    public function setAnswered(?\DateTime $answered): void { $this->answered = $answered; }
}

class EmcUserStateChange extends Base
{
    #[BsonSerialize]
    private ?\DateTime $date = null;

    #[BsonSerialize]
    private ?EmcUserState $userState = null;

    public function getDate(): ?\DateTime { return $this->date; }
    public function setDate(?\DateTime $date): void { $this->date = $date; }
    public function getUserState(): ?EmcUserState { return $this->userState; }
    public function setUserState(?EmcUserState $userState): void { $this->userState = $userState; }
}

class EmcAppointmentUser extends Base
{
    #[BsonSerialize]
    private ?EmcUserType $type = null;

    #[BsonSerialize]
    private ?EmcSurvey $answeredSurvey = null;

    #[BsonSerialize]
    private ?string $uuid = null;

    #[BsonSerialize]
    private ?ObjectId $mongoId = null;

    #[BsonSerialize]
    private ?string $email = null;

    #[BsonSerialize]
    private ?string $nhc = null;

    #[BsonSerialize]
    private ?string $name = null;

    #[BsonSerialize]
    private ?string $phone = null;

    #[BsonSerialize]
    private ?EmcUserState $currentState = null;

    /** @var EmcUserStateChange[] */
    #[BsonSerialize]
    private array $stateChanges = [];

    #[BsonSerialize]
    private ?\DateTime $leftOn = null;

    #[BsonSerialize]
    private ?\DateTime $joinedOn = null;

    #[BsonSerialize]
    private ?string $groupId = null;

    #[BsonSerialize]
    private ?string $subModule = null;

    public function getType(): ?EmcUserType { return $this->type; }
    public function setType(?EmcUserType $type): void { $this->type = $type; }
    public function getAnsweredSurvey(): ?EmcSurvey { return $this->answeredSurvey; }
    public function setAnsweredSurvey(?EmcSurvey $answeredSurvey): void { $this->answeredSurvey = $answeredSurvey; }
    public function getUuid(): ?string { return $this->uuid; }
    public function setUuid(?string $uuid): void { $this->uuid = $uuid; }
    public function getMongoId(): ?ObjectId { return $this->mongoId; }
    public function setMongoId(?ObjectId $mongoId): void { $this->mongoId = $mongoId; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }
    public function getNhc(): ?string { return $this->nhc; }
    public function setNhc(?string $nhc): void { $this->nhc = $nhc; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): void { $this->phone = $phone; }
    public function getCurrentState(): ?EmcUserState { return $this->currentState; }
    public function setCurrentState(?EmcUserState $currentState): void { $this->currentState = $currentState; }
    /** @return EmcUserStateChange[] */
    public function getStateChanges(): array { return $this->stateChanges; }
    public function setStateChanges(array $stateChanges): void { $this->stateChanges = $stateChanges; }
    public function getLeftOn(): ?\DateTime { return $this->leftOn; }
    public function setLeftOn(?\DateTime $leftOn): void { $this->leftOn = $leftOn; }
    public function getJoinedOn(): ?\DateTime { return $this->joinedOn; }
    public function setJoinedOn(?\DateTime $joinedOn): void { $this->joinedOn = $joinedOn; }
    public function getGroupId(): ?string { return $this->groupId; }
    public function setGroupId(?string $groupId): void { $this->groupId = $groupId; }
    public function getSubModule(): ?string { return $this->subModule; }
    public function setSubModule(?string $subModule): void { $this->subModule = $subModule; }
}

class EmcLegalDocumentsItem extends Base
{
    #[BsonSerialize]
    private ?ObjectId $id = null;

    #[BsonSerialize]
    private ?\DateTime $acceptedAt = null;

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getAcceptedAt(): ?\DateTime { return $this->acceptedAt; }
    public function setAcceptedAt(?\DateTime $acceptedAt): void { $this->acceptedAt = $acceptedAt; }
}

class EmcUserLegalDocuments extends Base
{
    #[BsonSerialize]
    private ?EmcLegalDocumentsItem $privacyDoc = null;

    #[BsonSerialize]
    private ?EmcLegalDocumentsItem $conditionsDoc = null;

    public function getPrivacyDoc(): ?EmcLegalDocumentsItem { return $this->privacyDoc; }
    public function setPrivacyDoc(?EmcLegalDocumentsItem $privacyDoc): void { $this->privacyDoc = $privacyDoc; }
    public function getConditionsDoc(): ?EmcLegalDocumentsItem { return $this->conditionsDoc; }
    public function setConditionsDoc(?EmcLegalDocumentsItem $conditionsDoc): void { $this->conditionsDoc = $conditionsDoc; }
}

class EmcRegistrationData extends Base
{
    #[BsonSerialize]
    private ?string $firstName = null;

    #[BsonSerialize]
    private ?string $lastName = null;

    #[BsonSerialize]
    private ?\DateTime $birthdate = null;

    #[BsonSerialize]
    private ?string $nhc = null;

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): void { $this->firstName = $firstName; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): void { $this->lastName = $lastName; }
    public function getBirthdate(): ?\DateTime { return $this->birthdate; }
    public function setBirthdate(?\DateTime $birthdate): void { $this->birthdate = $birthdate; }
    public function getNhc(): ?string { return $this->nhc; }
    public function setNhc(?string $nhc): void { $this->nhc = $nhc; }
}

class EmcRegistrationActivity extends Base
{
    #[BsonSerialize]
    private ?\DateTime $createdAt = null;

    #[BsonSerialize]
    private ?string $action = null;

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(?string $action): void { $this->action = $action; }
}

class EmcRegistrationRequest extends Base
{
    #[BsonSerialize(name: '_id')]
    private ?ObjectId $id = null;

    #[BsonSerialize]
    private ?\DateTime $createdAt = null;

    #[BsonSerialize]
    private ?\DateTime $updatedAt = null;

    #[BsonSerialize]
    private ?\DateTime $validatedAt = null;

    #[BsonSerialize]
    private ?bool $presential = false;

    #[BsonSerialize]
    private ?EmcRegistrationData $registrationData = null;

    #[BsonSerialize]
    private ?EmcDelegationType $delegationType = null;

    #[BsonSerialize]
    private bool $delegation = false;

    #[BsonSerialize]
    private bool $activation = false;

    #[BsonSerialize]
    private bool $patientActivation = false;

    #[BsonSerialize]
    private ?EmcRegStatus $status = null;

    /** @var EmcRegistrationActivity[] */
    #[BsonSerialize]
    private array $activity = [];

    #[BsonSerialize]
    private bool $migration = false;

    #[BsonSerialize]
    private bool $fromSap = false;

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): void { $this->updatedAt = $updatedAt; }
    public function getValidatedAt(): ?\DateTime { return $this->validatedAt; }
    public function setValidatedAt(?\DateTime $validatedAt): void { $this->validatedAt = $validatedAt; }
    public function getPresential(): ?bool { return $this->presential; }
    public function setPresential(?bool $presential): void { $this->presential = $presential; }
    public function getRegistrationData(): ?EmcRegistrationData { return $this->registrationData; }
    public function setRegistrationData(?EmcRegistrationData $registrationData): void { $this->registrationData = $registrationData; }
    public function getDelegationType(): ?EmcDelegationType { return $this->delegationType; }
    public function setDelegationType(?EmcDelegationType $delegationType): void { $this->delegationType = $delegationType; }
    public function isDelegation(): bool { return $this->delegation; }
    public function setDelegation(bool $delegation): void { $this->delegation = $delegation; }
    public function isActivation(): bool { return $this->activation; }
    public function setActivation(bool $activation): void { $this->activation = $activation; }
    public function isPatientActivation(): bool { return $this->patientActivation; }
    public function setPatientActivation(bool $patientActivation): void { $this->patientActivation = $patientActivation; }
    public function getStatus(): ?EmcRegStatus { return $this->status; }
    public function setStatus(?EmcRegStatus $status): void { $this->status = $status; }
    /** @return EmcRegistrationActivity[] */
    public function getActivity(): array { return $this->activity; }
    public function setActivity(array $activity): void { $this->activity = $activity; }
    public function addActivity(EmcRegistrationActivity $activity): void { $this->activity[] = $activity; }
    public function isMigration(): bool { return $this->migration; }
    public function setMigration(bool $migration): void { $this->migration = $migration; }
    public function isFromSap(): bool { return $this->fromSap; }
    public function setFromSap(bool $fromSap): void { $this->fromSap = $fromSap; }
}

#[BsonDiscriminator('type', [
    'LOGIN' => EmcLoginStatistic::class,
    'QUESTIONNAIRES' => EmcQuestionnaireStatistic::class,
    'REPORT_DOWNLOAD' => EmcReportDownloadStatistic::class,
])]
abstract class EmcStadistic extends EmcMongoBase
{
    #[BsonSerialize]
    protected ?ObjectId $userId = null;

    #[BsonSerialize]
    protected ?EmcStatType $type = null;

    #[BsonSerialize]
    protected int $count = 1;

    #[BsonSerialize]
    protected ?string $module = null;

    #[BsonSerialize]
    protected ?string $subModule = null;

    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getType(): ?EmcStatType { return $this->type; }
    public function setType(?EmcStatType $type): void { $this->type = $type; }
    public function getCount(): int { return $this->count; }
    public function setCount(int $count): void { $this->count = $count; }
    public function getModule(): ?string { return $this->module; }
    public function setModule(?string $module): void { $this->module = $module; }
    public function getSubModule(): ?string { return $this->subModule; }
    public function setSubModule(?string $subModule): void { $this->subModule = $subModule; }
}

class EmcLoginStatistic extends EmcStadistic
{
}

class EmcQuestionnaireStatistic extends EmcStadistic
{
    #[BsonSerialize]
    private ?ObjectId $patientId = null;

    #[BsonSerialize]
    private ?string $formId = null;

    #[BsonSerialize]
    private ?\DateTime $answeredAt = null;

    #[BsonSerialize]
    private ?\DateTime $availableAt = null;

    #[BsonSerialize]
    private ?\DateTime $reminderAt = null;

    #[BsonSerialize]
    private ?\DateTime $readyAt = null;

    public function getPatientId(): ?ObjectId { return $this->patientId; }
    public function setPatientId(?ObjectId $patientId): void { $this->patientId = $patientId; }
    public function getFormId(): ?string { return $this->formId; }
    public function setFormId(?string $formId): void { $this->formId = $formId; }
    public function getAnsweredAt(): ?\DateTime { return $this->answeredAt; }
    public function setAnsweredAt(?\DateTime $answeredAt): void { $this->answeredAt = $answeredAt; }
    public function getAvailableAt(): ?\DateTime { return $this->availableAt; }
    public function setAvailableAt(?\DateTime $availableAt): void { $this->availableAt = $availableAt; }
    public function getReminderAt(): ?\DateTime { return $this->reminderAt; }
    public function setReminderAt(?\DateTime $reminderAt): void { $this->reminderAt = $reminderAt; }
    public function getReadyAt(): ?\DateTime { return $this->readyAt; }
    public function setReadyAt(?\DateTime $readyAt): void { $this->readyAt = $readyAt; }
}

class EmcReportDownloadStatistic extends EmcStadistic
{
    #[BsonSerialize]
    private ?ObjectId $patientId = null;

    public function getPatientId(): ?ObjectId { return $this->patientId; }
    public function setPatientId(?ObjectId $patientId): void { $this->patientId = $patientId; }
}

// ========================================
// MIPA-BACKEND ALERTES PATTERNS
// ========================================

enum MipaAlertaOrigin: string
{
    case AlertesZero = 'projecte0';
    case HdomMessage = 'hdom_message';
}

enum MipaNotificationStatus: string
{
    case Success = 'SUCCESS';
    case Failure = 'FAILURE';
    case Pending = 'PENDING';
}

class MipaAlertaUser extends Base
{
    #[BsonSerialize]
    private ?ObjectId $id = null;

    #[BsonSerialize]
    private ?string $uuid = null;

    #[BsonSerialize]
    private bool $subscription = false;

    #[BsonSerialize]
    private bool $subscriptionAdmin = false;

    #[BsonSerialize]
    private ?\DateTimeInterface $seen = null;

    /** @var MipaAlertaDevice[] */
    #[BsonSerialize]
    private array $devices = [];

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getUuid(): ?string { return $this->uuid; }
    public function setUuid(?string $uuid): void { $this->uuid = $uuid; }
    public function isSubscription(): bool { return $this->subscription; }
    public function setSubscription(bool $subscription): void { $this->subscription = $subscription; }
    public function isSubscriptionAdmin(): bool { return $this->subscriptionAdmin; }
    public function setSubscriptionAdmin(bool $subscriptionAdmin): void { $this->subscriptionAdmin = $subscriptionAdmin; }
    public function getSeen(): ?\DateTimeInterface { return $this->seen; }
    public function setSeen(?\DateTimeInterface $seen): void { $this->seen = $seen; }
    /** @return MipaAlertaDevice[] */
    public function getDevices(): array { return $this->devices; }
    public function setDevices(array $devices): void { $this->devices = $devices; }
}

class MipaAlertaDevice extends Base
{
    #[BsonSerialize(name: '_id')]
    private ?ObjectId $id = null;

    #[BsonSerialize]
    private ?ObjectId $userId = null;

    #[BsonSerialize]
    private ?string $fcmToken = null;

    #[BsonSerialize]
    private ?MipaNotificationStatus $result = null;

    #[BsonSerialize]
    private ?\DateTimeInterface $seen = null;

    public function getId(): ?ObjectId { return $this->id; }
    public function setId(?ObjectId $id): void { $this->id = $id; }
    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getFcmToken(): ?string { return $this->fcmToken; }
    public function setFcmToken(?string $fcmToken): void { $this->fcmToken = $fcmToken; }
    public function getResult(): ?MipaNotificationStatus { return $this->result; }
    public function setResult(?MipaNotificationStatus $result): void { $this->result = $result; }
    public function getSeen(): ?\DateTimeInterface { return $this->seen; }
    public function setSeen(?\DateTimeInterface $seen): void { $this->seen = $seen; }
}

class MipaAssignedUser extends Base
{
    #[BsonSerialize]
    private ?ObjectId $userId = null;

    #[BsonSerialize]
    private ?\DateTime $at = null;

    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getAt(): ?\DateTime { return $this->at; }
    public function setAt(?\DateTime $at): void { $this->at = $at; }
}

class MipaHistoricRow extends Base
{
    #[BsonSerialize]
    private ?\DateTime $at = null;

    #[BsonSerialize]
    private ?string $alerta = null;

    #[BsonSerialize]
    private ?string $alertaDes = null;

    #[BsonSerialize]
    private bool $aillat = false;

    /** @var string[] */
    #[BsonSerialize]
    private array $aillatParams = [];

    #[BsonSerialize]
    private bool $corregit = false;

    #[BsonSerialize]
    private ?string $groupSol = null;

    #[BsonSerialize]
    private ?string $groupSeg = null;

    /** @var MipaAlertaUser[] */
    #[BsonSerialize]
    private array $users = [];

    /** @var MipaAlertaDevice[] */
    #[BsonSerialize]
    private array $devices = [];

    #[BsonSerialize]
    private ?MipaAssignedUser $assignedUser = null;

    public function getAt(): ?\DateTime { return $this->at; }
    public function setAt(?\DateTime $at): void { $this->at = $at; }
    public function getAlerta(): ?string { return $this->alerta; }
    public function setAlerta(?string $alerta): void { $this->alerta = $alerta; }
    public function getAlertaDes(): ?string { return $this->alertaDes; }
    public function setAlertaDes(?string $alertaDes): void { $this->alertaDes = $alertaDes; }
    public function isAillat(): bool { return $this->aillat; }
    public function setAillat(bool $aillat): void { $this->aillat = $aillat; }
    public function getAillatParams(): array { return $this->aillatParams; }
    public function setAillatParams(array $aillatParams): void { $this->aillatParams = $aillatParams; }
    public function isCorregit(): bool { return $this->corregit; }
    public function setCorregit(bool $corregit): void { $this->corregit = $corregit; }
    public function getGroupSol(): ?string { return $this->groupSol; }
    public function setGroupSol(?string $groupSol): void { $this->groupSol = $groupSol; }
    public function getGroupSeg(): ?string { return $this->groupSeg; }
    public function setGroupSeg(?string $groupSeg): void { $this->groupSeg = $groupSeg; }
    /** @return MipaAlertaUser[] */
    public function getUsers(): array { return $this->users; }
    public function setUsers(array $users): void { $this->users = $users; }
    /** @return MipaAlertaDevice[] */
    public function getDevices(): array { return $this->devices; }
    public function setDevices(array $devices): void { $this->devices = $devices; }
    public function getAssignedUser(): ?MipaAssignedUser { return $this->assignedUser; }
    public function setAssignedUser(?MipaAssignedUser $assignedUser): void { $this->assignedUser = $assignedUser; }
}

#[BsonDiscriminator('origin', [
    'projecte0' => MipaAlertesZeroAlerta::class,
    'hdom_message' => MipaHdomMessageAlerta::class,
])]
abstract class MipaAlerta extends EmcMongoBase
{
    #[BsonSerialize]
    protected ?ObjectId $user = null;

    #[BsonSerialize]
    protected ?string $origin = null;

    #[BsonSerialize]
    protected ?string $alertType = null;

    #[BsonSerialize]
    protected ?string $subscription = null;

    /** @var MipaAlertaUser[] */
    #[BsonSerialize]
    protected array $users = [];

    /** @var MipaAlertaDevice[] */
    #[BsonSerialize]
    protected array $devices = [];

    #[BsonSerialize]
    protected bool $deleted = false;

    public function getUser(): ?ObjectId { return $this->user; }
    public function setUser(?ObjectId $user): void { $this->user = $user; }
    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $origin): void { $this->origin = $origin; }
    public function getAlertType(): ?string { return $this->alertType; }
    public function setAlertType(?string $alertType): void { $this->alertType = $alertType; }
    public function getSubscription(): ?string { return $this->subscription; }
    public function setSubscription(?string $subscription): void { $this->subscription = $subscription; }
    /** @return MipaAlertaUser[] */
    public function getUsers(): array { return $this->users; }
    public function setUsers(array $users): void { $this->users = $users; }
    /** @return MipaAlertaDevice[] */
    public function getDevices(): array { return $this->devices; }
    public function setDevices(array $devices): void { $this->devices = $devices; }
    public function isDeleted(): bool { return $this->deleted; }
    public function setDeleted(bool $deleted): void { $this->deleted = $deleted; }
}

class MipaAlertesZeroAlerta extends MipaAlerta
{
    /** @var MipaHistoricRow[] */
    #[BsonSerialize]
    private array $historicArray = [];

    #[BsonSerialize]
    private bool $aillat = false;

    #[BsonSerialize]
    private bool $corregit = false;

    #[BsonSerialize]
    private ?string $alertaDes = null;

    #[BsonSerialize]
    private ?string $groupSeg = null;

    #[BsonSerialize]
    private ?string $groupSol = null;

    #[BsonSerialize]
    private ?MipaAssignedUser $assignedUser = null;

    /** @return MipaHistoricRow[] */
    public function getHistoricArray(): array { return $this->historicArray; }
    public function setHistoricArray(array $historicArray): void { $this->historicArray = $historicArray; }
    public function isAillat(): bool { return $this->aillat; }
    public function setAillat(bool $aillat): void { $this->aillat = $aillat; }
    public function isCorregit(): bool { return $this->corregit; }
    public function setCorregit(bool $corregit): void { $this->corregit = $corregit; }
    public function getAlertaDes(): ?string { return $this->alertaDes; }
    public function setAlertaDes(?string $alertaDes): void { $this->alertaDes = $alertaDes; }
    public function getGroupSeg(): ?string { return $this->groupSeg; }
    public function setGroupSeg(?string $groupSeg): void { $this->groupSeg = $groupSeg; }
    public function getGroupSol(): ?string { return $this->groupSol; }
    public function setGroupSol(?string $groupSol): void { $this->groupSol = $groupSol; }
    public function getAssignedUser(): ?MipaAssignedUser { return $this->assignedUser; }
    public function setAssignedUser(?MipaAssignedUser $assignedUser): void { $this->assignedUser = $assignedUser; }
}

class MipaHdomMessageAlerta extends MipaAlerta
{
    #[BsonSerialize]
    private ?string $nhc = null;

    #[BsonSerialize]
    private ?string $message = null;

    #[BsonSerialize]
    private ?string $name = null;

    public function getNhc(): ?string { return $this->nhc; }
    public function setNhc(?string $nhc): void { $this->nhc = $nhc; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): void { $this->message = $message; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
}

// ========================================
// EMC-BACKEND REHYDRATE+BSON PATTERNS
// ========================================

class EmcVerifyEmailRequest extends EmcMongoBase
{
    #[BsonSerialize]
    private ?ObjectId $userId = null;

    #[BsonSerialize]
    private ?string $password = null;

    #[BsonSerialize]
    private ?\DateTime $expirationDate = null;

    #[BsonSerialize]
    private ?\DateTime $verifyDate = null;

    #[BsonSerialize]
    private bool $used = false;

    #[BsonSerialize]
    private ?string $email = null;

    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): void { $this->password = $password; }
    public function getExpirationDate(): ?\DateTime { return $this->expirationDate; }
    public function setExpirationDate(?\DateTime $expirationDate): void { $this->expirationDate = $expirationDate; }
    public function getVerifyDate(): ?\DateTime { return $this->verifyDate; }
    public function setVerifyDate(?\DateTime $verifyDate): void { $this->verifyDate = $verifyDate; }
    public function isUsed(): bool { return $this->used; }
    public function setUsed(bool $used): void { $this->used = $used; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }
}

class EmcResetPasswordRequest extends EmcMongoBase
{
    #[BsonSerialize]
    private ?ObjectId $userId = null;

    #[BsonSerialize]
    private ?string $password = null;

    #[BsonSerialize]
    private ?\DateTime $expirationDate = null;

    #[BsonSerialize]
    private ?\DateTime $resetDate = null;

    #[BsonSerialize]
    private bool $used = false;

    #[BsonSerialize]
    private ?string $newEmail = null;

    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): void { $this->password = $password; }
    public function getExpirationDate(): ?\DateTime { return $this->expirationDate; }
    public function setExpirationDate(?\DateTime $expirationDate): void { $this->expirationDate = $expirationDate; }
    public function getResetDate(): ?\DateTime { return $this->resetDate; }
    public function setResetDate(?\DateTime $resetDate): void { $this->resetDate = $resetDate; }
    public function isUsed(): bool { return $this->used; }
    public function setUsed(bool $used): void { $this->used = $used; }
    public function getNewEmail(): ?string { return $this->newEmail; }
    public function setNewEmail(?string $newEmail): void { $this->newEmail = $newEmail; }
}

class EmcFcIntervals extends Base
{
    #[BsonSerialize]
    private int $zone1;

    #[BsonSerialize]
    private int $zone2;

    #[BsonSerialize]
    private int $zone3;

    #[BsonSerialize]
    private int $zone4;

    public function __construct(int $zone1 = 0, int $zone2 = 0, int $zone3 = 0, int $zone4 = 0)
    {
        $this->zone1 = $zone1;
        $this->zone2 = $zone2;
        $this->zone3 = $zone3;
        $this->zone4 = $zone4;
    }

    public function getZone1(): int { return $this->zone1; }
    public function setZone1(int $zone1): void { $this->zone1 = $zone1; }
    public function getZone2(): int { return $this->zone2; }
    public function setZone2(int $zone2): void { $this->zone2 = $zone2; }
    public function getZone3(): int { return $this->zone3; }
    public function setZone3(int $zone3): void { $this->zone3 = $zone3; }
    public function getZone4(): int { return $this->zone4; }
    public function setZone4(int $zone4): void { $this->zone4 = $zone4; }
}

class EmcSessionReport extends Base
{
    #[BsonSerialize]
    private ?float $max = null;

    #[BsonSerialize]
    private ?float $min = null;

    #[BsonSerialize]
    private ?\DateTime $createdAt = null;

    public function getMax(): ?float { return $this->max; }
    public function setMax(?float $max): void { $this->max = $max; }
    public function getMin(): ?float { return $this->min; }
    public function setMin(?float $min): void { $this->min = $min; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
}

class EmcSession extends EmcMongoBase
{
    #[BsonSerialize]
    private ?ObjectId $userId = null;

    #[BsonSerialize]
    private ?string $nhc = null;

    #[BsonSerialize]
    private ?ObjectId $appointmentId = null;

    #[BsonSerialize]
    private ?float $max = null;

    #[BsonSerialize]
    private ?float $min = null;

    #[BsonSerialize]
    private ?float $avg = null;

    #[BsonSerialize]
    private ?float $ini = null;

    /** @var EmcSessionReport[] */
    #[BsonSerialize]
    private array $reports = [];

    #[BsonSerialize]
    private ?EmcFcIntervals $fcIntervals = null;

    #[BsonSerialize]
    private bool $active = false;

    #[BsonSerialize]
    private bool $deviceConnected = false;

    #[BsonSerialize]
    private ?\DateTime $contactedRequestAt = null;

    public function getUserId(): ?ObjectId { return $this->userId; }
    public function setUserId(?ObjectId $userId): void { $this->userId = $userId; }
    public function getNhc(): ?string { return $this->nhc; }
    public function setNhc(?string $nhc): void { $this->nhc = $nhc; }
    public function getAppointmentId(): ?ObjectId { return $this->appointmentId; }
    public function setAppointmentId(?ObjectId $appointmentId): void { $this->appointmentId = $appointmentId; }
    public function getMax(): ?float { return $this->max; }
    public function setMax(?float $max): void { $this->max = $max; }
    public function getMin(): ?float { return $this->min; }
    public function setMin(?float $min): void { $this->min = $min; }
    public function getAvg(): ?float { return $this->avg; }
    public function setAvg(?float $avg): void { $this->avg = $avg; }
    public function getIni(): ?float { return $this->ini; }
    public function setIni(?float $ini): void { $this->ini = $ini; }
    /** @return EmcSessionReport[] */
    public function getReports(): array { return $this->reports; }
    public function setReports(array $reports): void { $this->reports = $reports; }
    public function getFcIntervals(): ?EmcFcIntervals { return $this->fcIntervals; }
    public function setFcIntervals(?EmcFcIntervals $fcIntervals): void { $this->fcIntervals = $fcIntervals; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function isDeviceConnected(): bool { return $this->deviceConnected; }
    public function setDeviceConnected(bool $deviceConnected): void { $this->deviceConnected = $deviceConnected; }
    public function getContactedRequestAt(): ?\DateTime { return $this->contactedRequestAt; }
    public function setContactedRequestAt(?\DateTime $contactedRequestAt): void { $this->contactedRequestAt = $contactedRequestAt; }
}