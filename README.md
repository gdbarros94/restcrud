# Documentação do Aplicativo REST CRUD

Este aplicativo é uma API RESTful que permite realizar operações CRUD (Create, Read, Update, Delete) em um banco de dados. Abaixo estão as instruções sobre como configurar e usar a aplicação, bem como uma descrição dos endpoints disponíveis.

## Configuração

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/gdbarros94/restcrud.git
   cd restcrud
   ```

2. **Instale as dependências necessárias** (se aplicável, por exemplo, se você estiver usando um gerenciador de pacotes como Composer).

3. **Configure o banco de dados:**
   - Abra o arquivo `config.php`.
   - Insira as credenciais corretas do banco de dados:
     - `$host`: endereço do host do banco de dados (ex: `localhost`).
     - `$dbname`: nome do banco de dados.
     - `$username`: nome de usuário do banco de dados.
     - `$password`: senha do banco de dados.

4. **Inicie o servidor web:**
   - Você pode usar um servidor embutido do PHP:
     ```bash
     php -S localhost:8000
     ```
   - Acesse a aplicação em `http://localhost:8000`.

## Endpoints

### 1. Autenticação

A autenticação é realizada com base na configuração do módulo de autenticação definido em `config.php`. A aplicação suporta diferentes métodos de autenticação:

- **Basic Auth (LDAP ou Chave Simples)**: Configure `authType` e as credenciais no arquivo de configuração.

### 2. Operações CRUD

- **GET /database**
  - Descrição: Recupera a lista de tabelas e suas relações do banco de dados.
  - Exemplo de requisição:
    ```
    GET http://localhost:8000/database
    ```

- **GET /{tableName}**
  - Descrição: Recupera dados de uma tabela específica.
  - Parâmetros: Os parâmetros de consulta podem ser passados na URL.
  - Exemplo de requisição:
    ```
    GET http://localhost:8000/users?id=1
    ```

- **POST /{tableName}**
  - Descrição: Insere novos dados em uma tabela.
  - Corpo da requisição: Deve conter um JSON com o formato:
    ```json
    [
        {
            "table": "nome_da_tabela",
            "columns": ["coluna1", "coluna2"],
            "values": ["valor1", "valor2"]
        }
    ]
    ```
  - Exemplo de requisição:
    ```
    POST http://localhost:8000/users
    ```

- **PUT /{tableName}**
  - Descrição: Atualiza dados existentes em uma tabela.
  - Corpo da requisição: Deve conter um JSON com o formato:
    ```json
    [
        {
            "table": "nome_da_tabela",
            "field": "campo_id",
            "comparator": "=",
            "value": "1",
            "columns": ["coluna1", "coluna2"],
            "values": ["novo_valor1", "novo_valor2"]
        }
    ]
    ```
  - Exemplo de requisição:
    ```
    PUT http://localhost:8000/users
    ```

- **DELETE /{tableName}**
  - Descrição: Deleta dados de uma tabela específica.
  - Corpo da requisição: Deve conter um JSON com o formato:
    ```json
    [
        {
            "table": "nome_da_tabela",
            "field": "campo_id",
            "comparator": "=",
            "value": "1"
        }
    ]
    ```
  - Exemplo de requisição:
    ```
    DELETE http://localhost:8000/users
    ```

## Respostas

As respostas da API serão sempre retornadas no formato JSON. Em caso de erro, a resposta incluirá uma chave `error` com uma descrição do erro. Caso contrário, os dados solicitados ou a confirmação de sucesso serão retornados.

## Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para abrir uma issue ou enviar um pull request.

## Licença

Este projeto está licenciado sob a MIT License. Veja o arquivo `LICENSE` para mais detalhes.