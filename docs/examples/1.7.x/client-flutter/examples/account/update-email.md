import 'package:appwrite/appwrite.dart';

Client client = Client()
    .setEndpoint('https://<REGION>.cloud.appwrite.io/v1') // Your API Endpoint
    .setProject('<YOUR_PROJECT_ID>'); // Your project ID

Account account = Account(client);

User result = await account.updateEmail(
    email: 'email@example.com',
    password: 'password',
);
