@extends("layouts.layout")

@section("content")

    <form action="{{route("send")}}" method="post" id="sendForm">

        @csrf

        <div class="input">
            <label for="first_name">Имя</label>
            <input type="text" name="first_name" id="first_name" min="3"
                   placeholder="Вася"
                   required
            >
        </div>

        <div class="input">
            <label for="last_name">Фамилия</label>
            <input type="text" name="last_name" id="last_name" min="3"
                   placeholder="Васечкин"
                   required
            >
        </div>

        <div class="input">
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" min="3"
                   placeholder="test@test.ru"
                   required
            >
        </div>

        <div class="input">
            <label for="phone">Телефон (10 символов)</label>
            <input type="tel" name="phone" id="phone" pattern="[0-9]{10}"
                   placeholder="9999999999"
                   required
            >
        </div>

        <div class="input">
            <label for="age">Возраст</label>
            <input type="text" name="age" id="age" min="1"
                   placeholder="18"
                   required
            >
        </div>

        <div class="input">
            <label for="gender">Мужской</label>
            <input type="radio" name="gender" id="gender" value="man" checked required>
        </div>

        <div class="input">
            <label for="gender">Женский</label>
            <input type="radio" name="gender" id="gender" value="woman" required>
        </div>

        <button id="sendButtonForm">Отправить</button>

    </form>

    <style>
        input,.input{
            display: block;
            margin-bottom: 10px;
        }
    </style>
    <script>

        let form = document.querySelector('#sendForm');

        form.onsubmit = function(event){

            let formData = new FormData(form);

            document.querySelector('#sendButtonForm').setAttribute("disabled", "disabled");

            fetch("{{route("send")}}",
                {
                    body: formData,
                    method: "post",
                    dataType: "json",
                    headers: {
                        "Accept": "application/json"
                    }
                }).then(function (response){

                    return response.json();

                }).then(function (response){

                    document.querySelector('#sendButtonForm').removeAttribute("disabled");

                    form.reset();
                });

            return false;
        }

    </script>


@endsection
