import { Client, Account } from "@appwrite.io/console";

const client = new Client()
    .setEndpoint('https://<REGION>.cloud.appwrite.io/v1') // Your API Endpoint
    .setProject('<YOUR_PROJECT_ID>'); // Your project ID

const account = new Account(client);

const result = await account.createPushTarget(
    '<TARGET_ID>', // targetId
    '<IDENTIFIER>', // identifier
    '<PROVIDER_ID>' // providerId (optional)
);

console.log(result);
