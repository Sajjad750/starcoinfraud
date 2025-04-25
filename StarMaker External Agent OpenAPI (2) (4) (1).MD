# StarMaker External Agent OpenAPI

## 1. General Request and Response Structure

> Before starting, please contact StarMaker's official operations staff to obtain your configuration details: app-key and app-secret, and submit an IP whitelist.

### 1.1. General Request Headers

> When making API requests, the following information needs to be added to the request header:

| Parameter Name         | Type   | Description                                                         |
| -------------------- | ------ | ------------------------------------------------------------ |
| x-app-key            | string | Coin Seller entity identifier, the `app-key` assigned by Starmaker                            |
| x-request-id         | string | Request ID, a unique identifier for each request                                     |
| x-request-timestamp  | int64  | Timestamp in seconds                                               |
| x-request-sign       | string | Request signature, see `1.2. Request Signature Generation Rules`                                    |

### 1.2. Request Signature Generation Rules

Concatenate **`request-path`:`x-request-timestamp`:`request-body`** into a single string. Use the HMAC-SHA256 algorithm to generate the signature, using the key `app-secret` provided by StarMaker.

Below is a code example for requesting the  `/api/v3/external/agent/account` API to retrieve user account information.

#### golang

```golang
package main

import (
  "crypto/hmac"
  "crypto/sha256"
  "encoding/hex"
  "fmt"
  "hash"
  "time"
)

// Generate signature
func generateSignature(requestPath, timestamp, requestBody, secretKey string) string {
  // Concatenate the request path, timestamp, and requestBody into a single string
  message := fmt.Sprintf("%s:%s:%s", requestPath, timestamp, requestBody)

  // Create a new HMAC-SHA256 hash instance
  h := hmac.New(sha256.New, []byte(secretKey))

  // Write the message
  h.Write([]byte(message))

  // Compute the hash value
  hashBytes := h.Sum(nil)

  // Convert the hash value to a hexadecimal string
  signature := hex.EncodeToString(hashBytes)

  return signature
}

func main() {
  // Example request path
  requestPath := "/api/v3/external/agent/account"

  // Example request timestamp, typically the time when the request is made; using the current time here
  timestamp := fmt.Sprintf("%d", time.Now().Unix())

  // Example request body, replace with actual data as needed
  requestBody := ""

  // Secret key, assigned by StarMaker
  secretKey := "your_secret_key_here"

  // Generate signature
  signature := generateSignature(requestPath, timestamp, requestBody, secretKey)

  // Print signature
  fmt.Printf("Signature: %s\n", signature)
}
```

#### java

```java
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.Base64;

public class SignatureGenerator {

    /**
     * Generate signature
     *
     * @param requestPath request Path
     * @param timestamp   timestamp
     * @param requestBody request Body
     * @param secretKey   secret Key
     * @return Signature string
     */
    public static String generateSignature(String requestPath, String timestamp, String requestBody, String secretKey) {
        try {
            // Concatenate request path, timestamp, and request body into a string
            String message = requestPath + ":" + timestamp + ":" + requestBody;

            // Generate signature using HMAC-SHA256 algorithm
            Mac sha256HMAC = Mac.getInstance("HmacSHA256");
            SecretKeySpec secretKeySpec = new SecretKeySpec(secretKey.getBytes(StandardCharsets.UTF_8), "HmacSHA256");
            sha256HMAC.init(secretKeySpec);

            // Compute hash value
            byte[] hash = sha256HMAC.doFinal(message.getBytes(StandardCharsets.UTF_8));

            // Convert hash value to Base64 encoded string
            String signature = Base64.getEncoder().encodeToString(hash);

            return signature;
        } catch (Exception e) {
            throw new RuntimeException("Failed to generate signature", e);
        }
    }

    public static void main(String[] args) {
        // Example request path
        String requestPath = "/api/v3/external/agent/account";

        // Example timestamp, typically the time when the request is made; using the current time here
        String timestamp = String.valueOf(System.currentTimeMillis() / 1000);

        // Example request body, replace with actual data as needed
        String requestBody = "";

        // Secret key, assigned by StarMaker
        String secretKey = "your_secret_key_here";

        // Generate signature
        String signature = generateSignature(requestPath, timestamp, requestBody, secretKey);

        // Print signature
        System.out.printf("Signature: %s%n", signature);
    }
}
```

#### javascript

```javascript
const crypto = require('crypto');

/**
 * Generate signature
 * @param {string} requestPath - request Path
 * @param {string} timestamp - timestamp
 * @param {string} requestBody - request Body
 * @param {string} secretKey - secret Key
 * @returns {string} signature string
 */
function generateSignature(requestPath, timestamp, requestBody, secretKey) {
  // Concatenate request path, timestamp, and request body into a string
  const message = `${requestPath}:${timestamp}:${requestBody}`;

  // Generate signature using HMAC-SHA256 algorithm
  const hmac = crypto.createHmac('sha256', secretKey);
  hmac.update(message);
  const signature = hmac.digest('hex');

  return signature;
}

// Example request path
const requestPath = '/api/v3/external/agent/account';

// Example timestamp, typically the time when the request is made; using the current time here
const timestamp = Math.floor(Date.now() / 1000).toString();

// Example request body, replace with actual data as needed
const requestBody = '';

// Secret key, assigned by StarMaker
const secretKey = 'your_secret_key_here';

// Generate signature
const signature = generateSignature(requestPath, timestamp, requestBody, secretKey);

// Print signature
console.log('Signature:', signature);
```

### 1.3. Response Structure

> After the server processes the request, it will return with status code 200 by default. The response data structure is as follows:

```json
{
  "code": 0,
  "data": {},
  "msg": "OK",
  "request_id": "xxxxxxx"
}
```

|Name|Type|Required|Constraints|Chinese Name|Description|
|---|---|---|---|---|---|
|code|number|yes|--||status code|
|data|object|yes|--||data|
|msg|string|yes|--||message|
|request_id|string|yes|--||request ID|

#### Status Codes

|Status code|Description|Remarks|
|---|---|---|
|0|request succeeded|--|
|4010001|app key not found|--|
|4010002|signature not found|--|
|4010003|invalid signature|--|
|4010004|invalid timestamp|--|
|4010005|invalid IP|--|
|4030001|insufficient Coin balance|--|
|4030002|invalid Coin Seller identity|--|
|4030003|duplicate order|--|
|4030004|order creation failed|--|
|4030005|failed risk control check|--|
|4030006|invalid agent UID|--|
|4220001|parameter error|--|
|4220002|invalid order ID|--|
|4220003|invalid user ID|--|
|4220004|user account closed|--|
|5000001|unknown error|--|
|5000002|data error|--|

## 2. API Information

### GET Query Current Account Information

GET /api/v3/external/agent/account

#### Request parameters

|Name|Location|Type|Required|Description|
|---|---|---|---|---|
|agent_uid|query|string| yes |your actual user SID in StarMaker|

> Example response

```json
{
  "code": 4030006,
  "data": {},
  "msg": "AGENT_UID_ERR",
  "request_id": "xxxxxxx"
}
```

```json
{
  "code": 0,
  "data": {
    "total": 43073,
    "available": 40073,
    "status": 0
  },
  "msg": "OK",
  "request_id": "xxxxxxx"
}
```

#### Response result

| Status code | Status code meaning | Description | Remarks |
|---|---|---|---|
|200|[OK](https://tools.ietf.org/html/rfc7231#section-6.3.1)|--|--|

#### Return data structure

Status code **200**

|Name|Type|Required|Constraints|Chinese Name|Remarks|
|---|---|---|---|---|---|
|» code|number|yes|--||--|
|» data|object|yes|--||--|
|»» total|number|yes|--||total Coin balance|
|»» available|number|yes|--||available Coin balance（total-deposit）|
|»» status|number|yes|--||account status；0=normal，-1=frozen，-2=cancelled，-3= pending review：Coin Seller identity was just created or the Coin Seller identity was canceled and the account was restored after that，-4= pending revocation: the account can still sell Coins in this status|
|» msg|string|yes|--||--|
|» request_id|string|yes|--||--|

### GET Query Other User Information

GET /api/v3/external/agent/user

#### request parameters

|Name|Location|Type|Required|Description|
|---|---|---|---|---|
|sids|query|string| yes | use StarMaker user UID to query, separated by string|

> Example response

```json
{
  "code": 4220001,
  "data": {},
  "msg": "sids can not be empty",
  "request_id": "xxxxx"
}
```

```json
{
  "code": 0,
  "data": [
    {
      "avatar": "https://improxy.starmakerstudios.com/tools/im/200/production/users/xxxx/profile.jpg?ts=2",
      "name": "xxx",
      "sid": "xxxx"
    },
    {
      "avatar": "https://improxy.starmakerstudios.com/tools/im/200/production/users/xxxx/profile.jpg?ts=2",
      "name": "xxx",
      "sid": "xxxx"
    },
    {
      "avatar": "https://improxy.starmakerstudios.com/tools/im/200/production/users/xxxx/profile.jpg?ts=2",
      "name": "xxx",
      "sid": "xxxx"
    }
  ],
  "msg": "OK",
  "request_id": "xxxxx"
}
```

#### Response result

|Status code|Status code meaning|Description|Remarks|
|---|---|---|---|
|200|[OK](https://tools.ietf.org/html/rfc7231#section-6.3.1)|--|--|

#### Return data structure

Status code **200**

|Name|Type|Required|Constraints|Chinese Name|Remarks|
|---|---|---|---|---|---|
|» code|number|yes|--||--|
|» data|[object]|yes|--||--|
|»» avatar|string|yes|--||user avatar|
|»» name|string|yes|--||user nickname|
|»» sid|string|yes|--||user SID|
|» msg|string|yes|--||--|
|» request_id|string|yes|--||--|

### GET Query Order Information

GET /api/v3/external/agent/order

#### Request parameters

|Name|Location|Type|Required|Description|
|---|---|---|---|---|
|agent_uid|query|string| yes |your actual user UID in StarMaker|
|order_ids|query|string| yes |your order IDs you generated, separated by string|

> Example response

```json
{
  "code": 4030006,
  "data": {},
  "msg": "AGENT_UID_ERR",
  "request_id": "xxxxxxx"
}
```

```json
{
  "code": 0,
  "data": [
    {
      "agent_uid": "12666376951992244",
      "cid": "xxx",
      "currency": "USD",
      "gold": 1,
      "order_id": "xxxxxx",
      "price": 0.99,
      "status": 151,
      "sid": "xxxx"
    },
    {
      "agent_uid": "12666376951992244",
      "cid": "xxx",
      "currency": "USD",
      "gold": 1,
      "order_id": "xxxxxx",
      "price": 0.99,
      "status": 0,
      "sid": "xxxx"
    }
  ],
  "msg": "OK",
  "request_id": "xxxxxxx"
}
```

#### Response result

|Status code|Status code meaning|Description|Remarks|
|---|---|---|---|
|200|[OK](https://tools.ietf.org/html/rfc7231#section-6.3.1)|--|--|

#### Return data structure

Status code **200**

|Name|Type|Required|Constraints|Chinese Name|Description|
|---|---|---|---|---|---|
|» code|number|yes|--||--|
|» data|[object]|yes|--||--|
|»» agent_uid|string|yes|--||your actual user UID in StarMaker|
|»» cid|string|yes|--||StarMaker order ID|
|»» currency|string|yes|--||currency string paid by the user, e.g., USD|
|»» gold|number|yes|--||number of Coins you paid|
|»» order_id|string|yes|--||your order ID|
|»» price|number|yes|--||amount paid by the user (float)|
|»» status|number|yes|--||order status: 0 = payment successful, 1 = payment declined, 151 = pending payment (usually due to insufficient Coin balance or being flagged by the risk control system, need to contact StarMaker's official operation staff for assistance)|
|»» sid|string|yes|--||SID of StarMaker user making the payment|
|» msg|string|yes|--||--|
|» request_id|string|yes|--||--|

### POST Create order

POST /api/v3/external/agent/create-order

> Body Request parameters

```json
{
  "agent_uid": "xxxxxx",
  "sid": "xxxxxx",
  "currency": "USD",
  "price": 1.5,
  "gold": 1,
  "order_id": "xxxxxx"
}
```

#### Request parameters

|Name|Location|Type|Required|Description|
|---|---|---|---|---|
|body|body|object| yes |--|
|» agent_uid|body|string| yes |your actual user UID in StarMaker|
|» sid|body|string| yes |SID of the user who made the payment|
|» currency|body|string| yes |currency string paid by the user, e.g., USD|
|» price|body|number| yes |amount paid by the user (float)|
|» gold|body|number| yes |Number of Coins you paid|
|» order_id|body|string| yes |your order ID|

> Example response

```json
{
  "code": 0,
  "data": {
    "agent_uid": "xxxxx",
    "cid": "xxxxx",
    "currency": "USD",
    "gold": 1,
    "order_id": "xxxxx",
    "price": 1.5,
    "status": 0,
    "sid": "xxxxx"
  },
  "msg": "OK",
  "request_id": "xxxxxxx"
}
```

```json
{
  "code": 4220001,
  "data": {},
  "msg": "PARAMS_ERR",
  "request_id": "xxxxxxx"
}
```

#### Response result

|Status code|Status code meaning|Description|Remarks|
|---|---|---|---|
|200|[OK](https://tools.ietf.org/html/rfc7231#section-6.3.1)|--|--|

#### Return data structure

Status code **200**

|Name|Type|Required|Constraints|Chinese Name|Description|
|---|---|---|---|---|---|
|» code|number|yes|--||--|
|» data|[object]|yes|--||--|
|»» agent_uid|string|yes|--||your actual user UID in StarMaker|
|»» cid|string|yes|--||StarMaker order ID|
|»» currency|string|yes|--||currency string paid by the user, e.g., USD|
|»» gold|number|yes|--||number of Coins you paid|
|»» order_id|string|yes|--||your order ID|
|»» price|number|yes|--||amount paid by the user (float)|
|»» status|number|yes|--||order status: 0 = payment successful, 1 = payment declined, 151 = pending payment (usually due to insufficient Coin balance or being flagged by the risk control system, need to contact StarMaker's official operation staff for assistance)|
|»» sid|string|yes|--||SID of StarMaker user making the payment|
|» msg|string|yes|--||--|
|» request_id|string|yes|--||--|