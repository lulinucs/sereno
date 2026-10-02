#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>
#include <Wire.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_BME280.h>

#include "config.h"

Adafruit_BME280 bme;
unsigned long ultimoEnvio = 0;

bool tentarWiFi(const char* ssid, const char* senha) {
  Serial.print("Tentando WiFi: ");
  Serial.println(ssid);

  WiFi.disconnect();
  delay(200);

  WiFi.begin(ssid, senha);

  // 20 × 500 ms = até 10 segundos por rede
  for (int tentativa = 0; tentativa < 20; tentativa++) {
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("WiFi conectado.");
      Serial.print("Rede: ");
      Serial.println(WiFi.SSID());
      Serial.print("IP: ");
      Serial.println(WiFi.localIP());
      Serial.print("Sinal: ");
      Serial.print(WiFi.RSSI());
      Serial.println(" dBm");
      return true;
    }

    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("Falha nesta rede.");
  return false;
}

void conectarWiFi() {
  Serial.println();
  Serial.println("Procurando WiFi...");

  WiFi.mode(WIFI_STA);

  if (tentarWiFi(WIFI_SSID_1, WIFI_PASSWORD_1)) {
    return;
  }

  Serial.println("Tentando rede reserva...");

  if (tentarWiFi(WIFI_SSID_2, WIFI_PASSWORD_2)) {
    return;
  }

  Serial.println("Nenhuma rede WiFi disponivel.");
}

void enviarLeitura() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi desconectado; tentando reconectar.");
    conectarWiFi();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("Leitura nao enviada: sem WiFi.");
      return;
    }
  }

  const float temperatura = bme.readTemperature();
  const float umidade = bme.readHumidity();
  const float pressao = bme.readPressure() / 100.0F;

  if (isnan(temperatura) || isnan(umidade) || isnan(pressao)) {
    Serial.println("Leitura nao enviada: erro no BME280.");
    return;
  }

  String payload = "{\"device_id\":\"" DEVICE_ID "\",";
  payload += "\"temperatura\":" + String(temperatura, 2) + ",";
  payload += "\"umidade\":" + String(umidade, 2) + ",";
  payload += "\"pressao\":" + String(pressao, 2) + "}";

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient https;
  String endpoint = String(API_URL) + "?action=push";
  if (!https.begin(client, endpoint)) {
    Serial.println("Leitura nao enviada: falha ao iniciar HTTPS.");
    return;
  }

  https.setTimeout(10000);
  https.addHeader("Content-Type", "application/json");
  if (strlen(DEVICE_TOKEN) > 0) {
    https.addHeader("X-Device-Token", DEVICE_TOKEN);
  }

  Serial.print("Enviando leitura de ");
  Serial.println(DEVICE_ID);

  const int httpCode = https.POST(payload);
  if (httpCode <= 0) {
    Serial.print("Falha HTTP: ");
    Serial.println(https.errorToString(httpCode));
    https.end();
    return;
  }

  const String resposta = https.getString();
  Serial.print("HTTP ");
  Serial.print(httpCode);
  Serial.print(": ");
  Serial.println(resposta);

  if (httpCode >= 200 && httpCode < 300 && resposta.indexOf("\"ok\":true") >= 0) {
    Serial.println("Leitura gravada com sucesso.");
  } else {
    Serial.println("Servidor rejeitou ou nao confirmou a leitura.");
  }

  https.end();
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println();
  Serial.print("Monitor da camara: ");
  Serial.println(DEVICE_ID);

  // NodeMCU: SDA = D2/GPIO4; SCL = D1/GPIO5.
  Wire.begin(4, 5);
  if (!bme.begin(0x76)) {
    Serial.println("BME280 nao encontrado no endereco 0x76.");
    while (true) {
      delay(1000);
    }
  }

  conectarWiFi();
  enviarLeitura();
  ultimoEnvio = millis();
}

void loop() {
  if (millis() - ultimoEnvio >= SEND_INTERVAL_MS) {
    ultimoEnvio = millis();
    enviarLeitura();
  }
  delay(100);
}
