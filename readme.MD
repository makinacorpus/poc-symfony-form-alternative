# An alternative to the Symfony Form Component

** ⚠️⚠️ This is a POC ⚠️⚠️ **

This repository is a proove of concept of ana alternative way to handle forms in Symfony without the Form Component.

It illustrates this article on our blog : https://makina-corpus.com/symfony/repenser-les-formulaires-symfony-une-approche-moderne
(see below for an english version)

To test it, use the [Symfony CLI](https://symfony.com/download) and [Composer](https://getcomposer.org/):

```sh

composer install
symfony server:start

```

## Why and How

(english translation of [the original french article](https://makina-corpus.com/symfony/repenser-les-formulaires-symfony-une-approche-moderne))

[Symfony's Form component](https://symfony.com/doc/current/forms.html) is the component we love to hate:
it covers 75% of our needs with ease, but for the remaining 25%, it can quickly become hell!

Among the points of friction, we can mention:

* Theming
* The use of Data Transformers
* The creation of Form Types, which can be cumbersome
* The creation of complex widgets/inputs (which involves almost all of the above points)
* Multistep management ([but this seems to have improved since 7.4](https://symfony.com/blog/new-in-symfony-7-4-multi-step-forms))

After [a quick call to the community on Mastodon](https://elk.zone/mamot.fr/@enuts_/115973459478395057),
I didn't get much feedback on this component, but no one tried to defend it. Looking at the comments, it's
clear that, as we said in the introduction, as soon as you stray a little from the nominal case, things get complicated!

But we don't want to seem like we're biting the hand that feeds us, the Form component has served us well for years.
We just wanted to try something else. In particular, we no longer want to have interface elements (labels, help texts, etc.)
defined in the controllers; we would like to confine them to Twig templates. Controllers should only be responsible for backend logic.

The idea is to try to get something like what you get with an app that has a strong backend/frontend split
(like Symfony + Vue.js, for example):

* the controller is responsible only for data validation and actions to be performed in case of success,
* the templates take care of everything related to the visual appearance of the form.

We had been thinking about this for some time, and the arrival of Symfony's new [MapQueryString and MapRequestPayload](https://symfony.com/doc/current/controller.html#automatic-mapping-of-the-request)
gave us what we were missing. We then adapted their logic to manage our forms.

### The **MapForm­State** attribute

So we came up with a new *MapFormState* attribute that works in the same way as *MapRequestPayload*.
Except that in the event of a validation error, instead of returning a 422 response containing the
list of errors, the process continues.

The controller then receives a *FormState* object containing two properties:

- *object*: the DTO (Data Transfer Object) populated with data from the form,
- *violationList*: an object containing any validation errors (based on the *ConstraintViolationList* returned by Symfony's Validator component).

The controller can then process the data if it is valid, or return the form with the validation errors if not.

```php
#[Route('/my-form')]
public function myForm(
    #[MapFormState(MyDto::class)] FormState $formState,
): Response {
    if ($formState->isValid()) {
        // Do your things here getting your submitted
        // data with: $formState->object
    }

    return $this->render('my_form.html.twig', [
        'values' => $formState->object,
        'errors' => $formState->violationList,
    ]);
}
```

### The *Form­Sta­te­Va­lueResol­ver*

Controller parameters of type *FormState* are resolved by an [*ArgumentResolver*](https://symfony.com/doc/current/controller/value_resolver.html): the *FormStateValueResolver*.
It is largely inspired by the [*RequestPayloadValueResolver*](https://github.com/symfony/symfony/blob/7.3/src/Symfony/Component/HttpKernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php).

Its purpose is to detect any *FormState* type object in a controller parameter and hydrate it.

Basically, this involves the following steps:

* Retrieve the request data from the Request object
* Denormalize the data in a DTO using Symfony's Serializer component
* Validate the object with the constraints defined directly on the DTO using the Validator component
* Return a FormState object, consisting of the DTO and the list of validation errors

Find the complete code for this ValueResolver [here](https://github.com/makinacorpus/poc-symfony-form-alternative/blob/main/src/FormState/FormStateValueResolver.php).

### First example (raw HTML)

Let's take a look at a concrete example. Imagine a form asking for:

    A username: a string containing only alphanumeric characters and hyphens
    An age: an integer between 7 and 77
    An email address
    A message: plain text

The DTO:

```php
readonly class MyDto {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Regex('/^[a-z]+(?:-[a-z]+)*$/')]
        public string $name,
        #[Assert\Email]
        public string $email,
        #[Assert\GreaterThanOrEqual(7)]
        #[Assert\LessThanOrEqual(77)]
        public int $age,
        public ?string $message = null,
    )  { }
}
```

The controller:

```php
use App\FormState\FormState;
use App\FormState\MapFormState;
use App\Dto\MyDto;

// ...

class IndexController extends AbstractController
{
    #[Route('/simple-form')]
    public function simpleForm(
        #[MapFormState(MyDto::class)] FormState $formState,
    ): Response {
        if ($formState->isValid()) {

            // Do your thing here !

            $submitted = print_r($formState->object, true);

            $this->addFlash('succes', "
                Submission ok!
                Submitted data:
                $submitted
            ");

            return $this->redirect('/');
        }

        return $this->render('simple_form.html.twig', [
            'values' => $formState->object,
            'errors' => $formState->violationList,
        ]);
    }
}
```

The Twig template:
```html
{% extends './base.html.twig' %}
{% block title %}An alternative to the Symfony Form Component{% endblock %}
{% block body %}
    <h1>An example of a simple form</h1>
    <form method="POST">
        <div>
            <label for="name" style="{{ errors.name ? 'color: red;' : '' }}">Name</label>
            <input
                id="name"
                name="name"
                required
                value="{{ values ? values.name }}"
            />
            {% for error in errors.name %}
                <p style="color: red">{{ error }}</p>
            {% endfor %}
            <div id="help-name">
                <p>Only letters and '-' accpeted.</p>
            </div>
        </div>

        <div>
            <label for="email" style="{{ errors.email ? 'color: red;' : '' }}">Email</label>
            <input
                id="email"
                name="email"
                required
                type="email"
                value="{{ values ? values.email }}"
            />
            {% for error in errors.email %}
                <p style="color: red">{{ error }}</p>
            {% endfor %}
        </div>

        <div>
            <label for="age" style="{{ errors.age ? 'color: red;' : '' }}">Age</label>
            <input
                id="age"
                name="age"
                type="number"
                value="{{ values ? values.age }}"
            />
            {% for error in errors.age %}
                <p style="color: red">{{ error }}</p>
            {% endfor %}
        </div>

        <div>
            <label for="message" style="{{ errors.message ? 'color: red;' : '' }}">Message</label>
            <textarea
                id="message"
                name="message"
                value="{{ values ? values.age }}"
            ></textarea>
            {% for error in errors.message %}
                <p style="color: red">{{ error }}</p>
            {% endfor %}
        </div>

        <button type="submit">Submit</button>
    </twig:Form>
{% endblock %}
```

Well, you're probably thinking:

> Wait, is that their solution? It's still a lot of work to write all those forms by hand in Twig templates!

And you're absolutely right, even more so since our example isn't complete! For example, some accessibility
elements are missing (such as aria-describedby for help messages and errors).

Let's see how we can improve all this using Twig Components.

### The power of Twig components

The arrival of Twig components, which could be roughly considered as *Twig macros on steroids*,
has made it easier to reuse Twig code. This has opened up a whole range of possibilities.

Building our graphical interface through composition is, to a certain extent, similar to what
can be done with tools such as Vue.js.

With this in mind, we created components for our form elements:

* A Form component to manage the form envelope and the CSRF token
* An Input component to manage classic form elements (text, number, email)
* A TextArea component
* An InputPassword component
* etc.

These components allow us to manage:

* The field label
* The value
* Errors
* Help text
* Accessibility
* etc.

From there, we can build our form with our components, adding new ones to our library as new needs arise.

## Second example (with Twig components)

Here is what the Twig template for the previous example would now look like:

```html
{% extends './base.html.twig' %}
{% block title %}An alternative to the Symfony Form Component{% endblock %}
{% block body %}
    <h1>An example of a simple form</h1>
    <twig:Form method="POST">
        <twig:Input
            name="name"
            label="Name"
            required
            help="Only letters and '-' accepted."
            :value="values ? values.name"
            :errors="errors.name"
        />
        <twig:Input
            name="email"
            label="Email"
            required
            type="email"
            :value="values ? values.email"
            :errors="errors.email"
        />
        <twig:Input
            name="age"
            label="Age"
            type="number"
            :value="values ? values.age"
            :errors="errors.age"
        />
        <twig:TextArea
            name="message"
            label="Message"
            :value="values ? values.age"
            :errors="errors.message"
        />
        <button type="submit">Submit</button>
    </twig:Form>
{% endblock %}
```

That's better, isn't it?

### Reusability

Thanks to the flexibility of the HttpKerl and Serializer components, it is easy to create reusable form elements.

Let's take a macro field representing an address. We want to be able to reuse this type of data for several forms in our application.

To do this, we create the DTO associated with our address data:

```php
readonly class AddressDto {
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $locality,
        public string $country,
    )  { }
}
```

The associated Twig component:

```html
<fieldset id="{{ id }}">
    <legend>{{ label }}</legend>
    <twig:Input
        :name="name ~ '[street]'"
        label="Street"
        required
        :value="value ? value.street"
    />
    <twig:Input
        :name="name ~ '[postalCode]'"
        label="Postal code"
        required
        :value="value ? value.postalCode"
    />
    <twig:Input
        :name="name ~ '[locality]'"
        label="Locality"
        required
        :value="value ? value.locality"
    />
    <twig:Input
        :name="name ~ '[country]'"
        label="Country"
        required
        :value="value ? value.country"
    />
</fieldset>
```

It can then be used in our forms:

The DTO:

```php
readonly class CompositeFormDto {
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Regex('/^[a-z]+(?:-[a-z]+)*$/')]
        public string $name,
        #[Assert\Email]
        public string $email,
        public AddressDto $address,
    ) { }
}
```

And the associated Twig template:

```html
{% extends './base.html.twig' %}
{% block title %}An alternative to the Symfony Form Component{% endblock %}
{% block body %}
    <h1>An example of composite form</h1>
    <twig:Form method="POST">
        <twig:Input
            name="email"
            label="Email"
            required
            type="email"
            :value="values ? values.email"
            :errors="errors.email"
        />
        <twig:AddressInput
            name="address"
            label="Address"
            :value="values ? values.address"
            :errors="errors.address"
        />
        <button type="submit">Submit</button>
    </twig:Form>
{% endblock %}
```

*Et voilà*, there's nothing else to do!

Note: this technique also works with the *MapQueryString* and *MapRequestPayload* attributes.

### Conclusion

Looking at the given examples, one might think that our approach is overly verbose: it seems
like a high price to pay. It's true that for simple forms, the advantages are not obvious.

This approach is (perhaps) a little more complex to learn and implement.

But it is when we come to more complex forms that we realize how easy it is to handle tricky
cases. It is when developing a new specific input that we appreciate the strong decoupling between
the frontend and the backend.

If you are interested in our approach, feel free to discuss it with us in the Discussions section of
this GitHub repository.

In summary, the advantages of this way of managing forms are:

Strong decoupling between backend and frontend logic

* Validation management via DTOs in the manner of a decoupled application (such as *MapQueryString* and *MapRequestPayload*)
* Ability to easily customize front-end rendering via its library of form components
* Simplified management of complex components

And its limitations:

* Not very well suited to form generation (dynamic forms created programmatically)
* Slightly more verbose approach (Twig templates are more complex than with the Form component, but the controllers are relieved!)
* The Mapped-Payload-Value-Resolver still needs to be refined to make it completely generic (particularly for file management).
