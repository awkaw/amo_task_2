<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFields\CustomFieldsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\Leads\Pipelines\PipelinesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\TagsCollection;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Filters\EntitiesLinksFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\AccountModel;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\CatalogModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFields\NumericCustomFieldModel;
use AmoCRM\Models\CustomFields\TextCustomFieldModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\TagModel;
use AmoCRM\Models\TaskModel;
use AmoCRM\Models\UserModel;
use App\Models\PersonalAccessToken;
use App\Services\AmoCRM\AmoCRMService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Type\Integer;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected $amo = null;
    protected $first_name = null;
    protected $last_name = null;
    protected $phone = null;
    protected $email = null;
    protected $gender = null;

    public function __construct()
    {
        $this->amo = (new AmoCRMService())->create_client();
    }

    public function home(Request $request)
    {

        if($request->has("code")){

            return response()->redirectTo("/saveToken?".http_build_query([
                "code" => $request->get("code"),
                "state" => $request->get("state"),
                "referer" => $request->get("referer"),
                "platform" => $request->get("platform"),
                "client_id" => $request->get("client_id"),
            ]));
        }

        if(is_null($this->amo)){
            return response()->redirectToRoute("getToken");
        }

        return view("home");
    }

    public function send(Request $request)
    {
        $this->amo = (new AmoCRMService())->create_client();

        $request->validate([
            "first_name" => "required|string:min:3",
            "last_name" => "required|string:min:3",
            "gender" => "required|string:3",
            "age" => "required|decimal:0",
            "phone" => "required|string:min:7",
            "email" => "required|email",
        ]);

        $this->first_name = $request->get("first_name");
        $this->last_name = $request->get("last_name");
        $this->gender = $request->get("gender");
        $this->phone = $request->get("phone");
        $this->email = $request->get("email");
        $this->age = $request->get("age");

        $user_id = $this->get_user();

        // Получаем контакт, проверяя на дубль
        $contact = $this->get_contact();

        // Получаем сделку существующего контакта
        if($contact !== null){

            $leads = $this->get_leads_from_contact($contact);

            if($leads)
            {
                foreach ($leads as $lead) {

                    if($lead instanceof LeadModel)
                    {
                        $this->amo->leads()->get();

                        if($lead->getStatusId() == 142)
                        {
                            // Добавляем покупателя
                            $customerModel = $this->add_customer($user_id, $contact);

                            // Добавим тег
                            $tagModel = new TagModel();
                            $tagModel->setName($lead->getName());

                            $tagsCollection = new TagsCollection();
                            $tagsCollection->add($tagModel);

                            $customerModel->setTags($tagsCollection);
                        }
                    }
                }
            }
        }

        // Если нет дубля, то создаем контакт
        if($contact === null){
            $contact = $this->add_contact($user_id, $this->amo->account()->getCurrent());
        }

        // Добавляем сделку
        $newLeadModel = $this->add_lead($user_id, $contact);

        // Добавляем товары
        $this->add_products($newLeadModel);

        // Добавить задачу
        $this->add_task($user_id, $newLeadModel);

        return [
            "code" => 200,
            "message" => "success",
        ];
    }

    protected function add_lead(int $user_id, ContactModel $contactModel): LeadModel
    {
        $leadModel = new LeadModel();

        $leadModel->setName("Сделка {$user_id} ".Carbon::now()->format("Y-m-d His"));

        $leadModel->setAccountId($contactModel->getAccountId());

        $leadModel->setResponsibleUserId($user_id);

        $links = new LinksCollection();

        $links->add($contactModel);

        $leadModel = $this->amo->leads()->addOne($leadModel);

        $this->amo->leads()->link($leadModel, $links);

        return $leadModel;
    }

    protected function add_products(LeadModel $leadModel): void
    {
        $catalogsCollection = $this->amo->catalogs()->get();
        $catalog = $catalogsCollection->getBy('name', 'Товары');

        $catalogElementsCollection = new CatalogElementsCollection();

        define("televisor", "Телевизор");
        define("magnitofon", "Магнитофон");

        $productModel1 = new CatalogElementModel();
        $productModel1->setName(televisor);
        $productModel1->setQuantity(1);

        $productModel2 = new CatalogElementModel();
        $productModel2->setName(magnitofon);
        $productModel2->setQuantity(1);

        $catalogElementsCollection->add($productModel1);
        $catalogElementsCollection->add($productModel2);

        $catalogElementsService = $this->amo->catalogElements($catalog->getId());
        $catalogElementsService->add($catalogElementsCollection);

        $televisorElement = $catalogElementsCollection->getBy('name', televisor);
        $televisorElement->setQuantity(1);

        $magnitofonElement = $catalogElementsCollection->getBy('name', magnitofon);
        $magnitofonElement->setQuantity(1);

        $links = new LinksCollection();
        $links->add($televisorElement);
        $links->add($magnitofonElement);

        $this->amo->leads()->link($leadModel, $links);
    }

    protected function add_task(int $user_id, LeadModel $leadModel): void
    {
        $taskModel = new TaskModel();

        $createdAt = Carbon::now();

        $createdAt->hour = 9;
        $createdAt->minute = 0;
        $createdAt->second = 0;

        if($createdAt->hour > 18 && $createdAt->minute > 0 && $createdAt->second > 0){
            $createdAt->addDays(1);
        }

        if($createdAt->dayOfWeek == 0){
            $createdAt->addDays(1);
        }

        if($createdAt->dayOfWeek == 6){
            $createdAt->addDays(2);
        }

        $dateEnd = Carbon::now()->addDays(4);

        $dateEnd->hour = 9;
        $dateEnd->minute = 0;
        $dateEnd->second = 0;

        if($dateEnd->hour > 17 && $dateEnd->minute > 59 && $dateEnd->second > 59){
            $dateEnd->addDay();
        }

        if($dateEnd->dayOfWeek == 0){
            $dateEnd->addDays(1);
        }

        if($dateEnd->dayOfWeek == 6){
            $dateEnd->addDays(2);
        }

        $taskModel->setResponsibleUserId($user_id);
        $taskModel->setCreatedAt($createdAt->unix());
        $taskModel->setCompleteTill($dateEnd->unix());
        $taskModel->setDuration(9 * 60 * 60);
        $taskModel->setTaskTypeId(TaskModel::TASK_TYPE_ID_CALL);
        $taskModel->setText('Новая задача');
        $taskModel->setEntityType(EntityTypesInterface::LEADS);
        $taskModel->setEntityId($leadModel->getId());

        $tasksCollection = new TasksCollection();

        $tasksCollection->add($taskModel);

        $this->amo->tasks()->add($tasksCollection);
    }

    protected function add_customer(int $user_id, ContactModel $contactModel): CustomerModel
    {
        $customerModel = new CustomerModel();

        $customerModel->setName("Покупатель ".$contactModel->getFirstName()." ".$contactModel->getLastName());

        $customerModel->setResponsibleUserId($user_id);

        $customerModel->setNextDate(Carbon::now()->addDay()->unix());

        $customerService = $this->amo->customers();

        $customerModel = $customerService->addOne($customerModel);

        $links = new LinksCollection();

        $links->add($contactModel);

        $customerService->link($customerModel, $links);

        return $customerModel;
    }

    protected function get_leads_from_contact(ContactModel $contactModel): LeadsCollection
    {
        $contactLeads = new LeadsCollection();

        $links = $this->amo->links("contacts");
        $filter = new EntitiesLinksFilter([$contactModel->getId()]);
        $contactsLeads = $links->get($filter)->getBy('toEntityType', 'leads');

        if($contactsLeads)
        {
            $leads = $this->amo->leads()->getOne($contactsLeads->getToEntityId());
            //$leads = $this->amo->leads()->get($contactsLeads->getToEntityId());

            if($leads)
            {
                $contactLeads->add($leads);
            }
        }

        return $contactLeads;
    }

    protected function get_user(): int
    {
        $users = $this->amo->users()->get();

        $accountModel = $this->amo->account();

        $user_id = $accountModel->getCurrent()->getCurrentUserId();

        if($users->count() > 0)
        {
            $keys = array_keys($users->keys());
            $user_key = array_rand($keys);
            $user_id = $users->offsetGet($user_key)->getId();
        }

        if($user_id < 1)
        {
            throw new \Exception("Ошибка пользователя");
        }

        return $user_id;
    }

    protected function get_contact(): ?ContactModel
    {
        $duplicate = null;

        /*$amoService = new AmoCRMService();

        $subdomain = $amoService->getDomain();
        $access_token = $amoService->getAccessToken();

        $response = Http::withHeaders([
            "Authorization" => "Bearer {$access_token}",
            "Content-Type" => "application/json"
        ])
            ->withUserAgent("amoCRM-oAuth-client/1.0")
            ->get("https://".$subdomain."/api/v4/contacts")
        ;

        Log::debug(print_r($response->json(), true));
        Log::debug(print_r($response->body(), true));*/

        try{

            $contacts = $this->amo->contacts()->get();

            if($contacts->count() > 0)
            {
                foreach ($contacts as $contact) {

                    if($contact instanceof ContactModel){

                        $customFields = $contact->getCustomFieldsValues();

                        if($customFields){

                            $customPhone = $customFields->getBy("fieldCode", "PHONE");

                            if($customPhone->getValues()->first()->getValue() == $this->phone){

                                $duplicate = $contact;

                                break;
                            }
                        }
                    }
                }
            }

        }catch (\Exception $e)
        {

        }

        return $duplicate;
    }

    protected function add_fields(): void
    {
        $customFieldsService = $this->amo->customFields(EntityTypesInterface::CONTACTS);
        $customFieldsCollection = new CustomFieldsCollection();

        $result = $customFieldsService->get();

        if (!$result->getBy('code', 'SEX')) {
            $sex = new TextCustomFieldModel();
            $sex->setName('Пол')->setSort(30)->setCode('SEX');
            $customFieldsCollection->add($sex);
        }
        if (!$result->getBy('code', 'AGE')) {
            $age = new NumericCustomFieldModel();
            $age->setName('Возраст')->setSort(40)->setCode('AGE');
            $customFieldsCollection->add($age);
        }

        if (isset($sex) || isset($age)) {
            $customFieldsService->add($customFieldsCollection);
        }
    }

    public function add_contact(int $user_id, AccountModel $accountModel): ContactModel
    {
        $this->add_fields();

        $contactModel = new ContactModel();

        $contactModel->setName($this->first_name." ".$this->last_name);

        $contactModel->setFirstName($this->first_name);
        $contactModel->setLastName($this->last_name);

        $contactModel->setResponsibleUserId($user_id);
        $contactModel->setCreatedBy($user_id);
        $contactModel->setAccountId($accountModel->getId());

        $customFields = new CustomFieldsValuesCollection();

        $phoneField = $customFields->getBy('code', 'PHONE');

        if (empty($phoneField)) {
            $phoneField = (new TextCustomFieldValuesModel())->setFieldCode('PHONE');
        }

        $phoneField->setValues(
            (new TextCustomFieldValueCollection())
                ->add((new TextCustomFieldValueModel())->setValue($this->phone))
        );

        $customFields->add($phoneField);

        $emailField = $customFields->getBy('code', 'EMAIL');

        if (empty($emailField)) {
            $emailField = (new TextCustomFieldValuesModel())->setFieldCode('EMAIL');
        }

        $emailField->setValues(
            (new TextCustomFieldValueCollection())
                ->add((new TextCustomFieldValueModel())->setValue($this->email))
        );

        $customFields->add($emailField);

        $sexField = $customFields->getBy('code', 'SEX');

        if (empty($sexField)) {
            $sexField = (new TextCustomFieldValuesModel())->setFieldCode('SEX');
        }

        $sexField->setValues(
            (new TextCustomFieldValueCollection())
                ->add((new TextCustomFieldValueModel())->setValue($this->gender))
        );

        $customFields->add($sexField);

        $ageField = $customFields->getBy('code', 'AGE');

        if (empty($ageField)) {
            $ageField = (new NumericCustomFieldValuesModel())->setFieldCode('AGE');
        }

        $ageField->setValues(
            (new NumericCustomFieldValueCollection())
                ->add((new NumericCustomFieldValueModel())->setValue($this->age))
        );

        $customFields->add($ageField);

        $contactModel->setCustomFieldsValues($customFields);

        $this->amo->contacts()->addOne($contactModel);

        return $contactModel;
    }
}
