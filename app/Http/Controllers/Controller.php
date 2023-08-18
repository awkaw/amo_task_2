<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\Leads\Pipelines\PipelinesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Filters\EntitiesLinksFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\CatalogModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
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
                            $this->add_customer($contact);
                        }
                    }
                }
            }
        }

        // Если нет дубля, то создаем контакт
        if($contact === null){
            $contact = $this->add_contact();
        }

        // Добавляем сделку
        $newLeadModel = $this->add_lead($user_id, $contact);

        // Добавляем товары
        $this->add_products($newLeadModel);

        // Добавить задачу
        $this->add_task($user_id, $newLeadModel);

        /*$linksService = $this->amo->links(EntityTypesInterface::CONTACTS);

        $lead = $this->amo->leads()->getOne(1000);

        $contacts = $lead->getContacts();

        $lead->getContacts()->offsetGet(1);

        $link = new LinksCollection();

        $this->amo->leads()->link($lead, $link);*/

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

        $date = Carbon::now()->addDays(4);

        if($date->hour > 18 && $date->minute > 0){
            $date->addDay();
        }

        if($date->dayOfWeek == 0){
            $date->addDays(1);
        }

        if($date->dayOfWeek == 6){
            $date->addDays(2);
        }

        if($date->hour < 9){
            $date->hour = 9;
        }

        $taskModel->setResponsibleUserId($user_id);
        $taskModel->setDuration($date->unix());
        $taskModel->setCompleteTill($date->addDay()->unix());
        $taskModel->setTaskTypeId(TaskModel::TASK_TYPE_ID_CALL);
        $taskModel->setText('Новая задача 4 дня');
        $taskModel->setEntityType(EntityTypesInterface::LEADS);
        $taskModel->setEntityId($leadModel->getId());

        $tasksCollection = new TasksCollection();

        $tasksCollection->add($taskModel);

        $this->amo->tasks()->add($tasksCollection);
    }

    protected function add_customer(ContactModel $contactModel): void
    {
        $customerModel = new CustomerModel();

        $customerModel->setName($contactModel->getFirstName()." ".$contactModel->getLastName());

        $customers = $this->amo->customers();

        $customers->addOne($customerModel);

        $links = new LinksCollection();

        $links->add($contactModel);

        $customers->link($customerModel, $links);
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

        return $duplicate;
    }

    public function add_contact(): ContactModel
    {
        $contactModel = new ContactModel();

        $contactModel->setFirstName($this->first_name);
        $contactModel->setLastName($this->last_name);

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

        $this->amo->contacts()->addOne($contactModel);

        return $contactModel;
    }
}
