using Appwrite;
using Appwrite.Services;
using Appwrite.Models;

var client = new Client()
    .SetEndPoint("https://<REGION>.cloud.appwrite.io/v1") // Your API Endpoint
    .SetProject("5df5acd0d48c2") // Your project ID
    .SetKey("919c2d18fb5d4...a2ae413da83346ad2"); // Your secret API key

var users = new Users(client);

User result = await users.CreateScryptModifiedUser(
    userId: "[USER_ID]",
    email: "email@example.com",
    password: "password",
    passwordSalt: "[PASSWORD_SALT]",
    passwordSaltSeparator: "[PASSWORD_SALT_SEPARATOR]",
    passwordSignerKey: "[PASSWORD_SIGNER_KEY]");