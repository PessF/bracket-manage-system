# คู่มือ REST API ระบบจัดการแข่งขัน EasyKids

เอกสารนี้อธิบาย REST API รุ่น 1 สำหรับสร้างและควบคุมการแข่งขันผ่าน JSON ครอบคลุมการแข่งขันมาตรฐาน การแข่งขันแบบแบ่งกลุ่มต่อด้วย Playoff การจัดทีม การสร้างสาย การบันทึกคะแนน และการอ่านผลการแข่งขัน

เมื่อระบบทำงานแล้ว สามารถเปิดคู่มือฉบับนี้ผ่าน `GET /api/manual/th` และเปิดคู่มือ HTML ผ่าน `/api/docs`

## 1. Base URL และรูปแบบข้อมูล

Endpoint ทั้งหมดอยู่ใต้ URL:

```text
https://YOUR_HOST/api
```

ส่ง Header ต่อไปนี้กับ Request ที่มี JSON body:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer YOUR_ADMIN_TOKEN
Accept-Language: th-TH
```

สามารถใช้ `?lang=th` แทน `Accept-Language: th-TH` ได้

Response ที่สำเร็จมีรูปแบบ:

```json
{
  "success": true,
  "data": {}
}
```

Response ที่ไม่สำเร็จมีรูปแบบ:

```json
{
  "success": false,
  "error": {
    "message": "คำอธิบายข้อผิดพลาด",
    "fields": {
      "field_name": ["รายละเอียดการตรวจสอบข้อมูล"]
    }
  }
}
```

`error.fields` จะมีเฉพาะข้อผิดพลาดจากการตรวจสอบ Request body

## 2. การยืนยันตัวตนและ Scope

API ใช้ Administrator Bearer Token หนึ่งระดับ ยังไม่มี OAuth scope แยก `read` และ `write`

- `GET /api/health` และ `GET /api/capabilities` ใช้ได้โดยไม่ต้องมี Token
- GET ของงานแข่งขันและรายการแข่งขันอ่านได้โดยไม่ต้องใช้ Token ส่วนการสร้าง แก้ไข หรือลบข้อมูลต้องมี Administrator Bearer Token
- Token สามารถอ่านและแก้ไขการแข่งขันทั้งหมด จึงต้องเก็บไว้เฉพาะฝั่ง Server
- ห้ามใส่ Token ใน URL, JavaScript สาธารณะ, Mobile binary ที่ถอดได้ง่าย หรือ Git
- ลิงก์ `/view/{token}` สำหรับผู้ชมเป็นหน้าเว็บแบบอ่านอย่างเดียว ไม่ใช่ API Token

สร้างหรือยกเลิก Token ได้จากเมนู **การเข้าถึง API** ของผู้ดูแลระบบ

### โครงสร้างงานแข่งขัน → รายการแข่งขัน

หน้าแรกเป็นรายการงาน (`/events`) ภายในแต่ละงานมีรายการแข่งขันของงานนั้น โมเดล `Tournament` เดิมยังใช้แทนรายการแข่งขัน โดยเพิ่ม `event_id` อ้างอิง `Event` ข้อมูลเดิมทั้งหมดถูกจัดไว้ในงาน **Existing competitions** โดยไม่เปลี่ยน ID หรือผลการแข่งขัน

| Endpoint | สิทธิ์ |
| --- | --- |
| `GET /api/events` และ `GET /api/events/{event}` | สาธารณะ |
| `GET /api/events/{event}/competitions` | สาธารณะ |
| `GET /api/events/{event}/competitions/{id}` | สาธารณะ |
| `GET /api/events/{event}/competitions/{id}/bracket` | สาธารณะ |
| `POST /api/events` | ผู้ดูแล |
| `PUT/PATCH/DELETE /api/events/{event}` | ผู้ดูแล |
| `POST /api/events/{event}/competitions` | ผู้ดูแล |
| `PUT/PATCH/DELETE /api/events/{event}/competitions/{id}` | ผู้ดูแล |

ฟิลด์งานคือ `name` (จำเป็น), `description`, `venue`, `starts_on`, `ends_on` วันที่ใช้ `YYYY-MM-DD` และวันสิ้นสุดต้องไม่ก่อนวันเริ่มต้น ลบงานที่ยังมีรายการแข่งขันไม่ได้ (409) หาก ID รายการแข่งขันไม่อยู่ในงานตาม URL จะตอบ 404

การย้ายรายการแข่งขันทำได้ด้วย `PATCH /api/tournaments/{id}` และส่ง `event_id` ใหม่ การสร้างผ่าน URL ซ้อนจะใช้งานจาก URL เสมอ ระบบเดิมที่สร้างผ่าน `/api/tournaments` โดยไม่ส่ง `event_id` จะถูกจัดเข้า **Existing competitions** ส่วนรายการแบบแบนรองรับตัวกรอง `?event_id={uuid}`

ตัวอย่างตรวจสอบ Token:

```bash
curl "https://YOUR_HOST/api/tournaments?per_page=1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Accept: application/json"
```

## 3. ตรวจสอบความสามารถของ Server

```http
GET /api/capabilities
```

Endpoint นี้คืนค่ารูปแบบการแข่งขัน โครงสร้าง ประเภท Ranking วิธี Seed สถานะ และขีดจำกัดที่ Server รองรับ เหมาะสำหรับสร้าง Integration ที่ไม่เขียนค่าคงที่เอง

รูปแบบการแข่งขันที่รองรับ:

| ค่า | การใช้งาน |
|---|---|
| `RANKING` | แข่งขันจากผลหลายครั้ง เช่น Racing Robot และ Drone Mission |
| `ROUND_ROBIN` | ทุกทีมพบกันและจัดอันดับจากผลการแข่งขัน |
| `SINGLE_ELIMINATION` | แพ้ครั้งเดียวตกรอบ |
| `DOUBLE_ELIMINATION` | แพ้สองครั้งตกรอบ รองรับ Grand Final 1 หรือ 2 นัด |

โครงสร้างที่รองรับ:

| ค่า | การใช้งาน |
|---|---|
| `STANDARD` | มี Main Stage หนึ่งรอบตาม `format` |
| `ADVANCED` | มี Group Stage หลายกลุ่ม แล้วเลื่อนทีมเข้า Playoff |

ประเภท Ranking:

| ค่า | ข้อมูลผลการแข่งขัน |
|---|---|
| `RACING_ROBOT` | เวลาต่อรอบ ค่าน้อยกว่าดีกว่า |
| `DRONE_MISSION` | คะแนน Manual + Automatic ค่าสูงกว่าดีกว่า แล้วใช้เวลาน้อยกว่าเป็น Tie-break |

## 4. ลำดับสถานะการแข่งขัน

ลำดับทั่วไป:

```text
DRAFT -> READY -> LIVE -> COMPLETED -> ARCHIVED
```

- สร้างการแข่งขันใหม่แล้วสถานะเป็น `DRAFT`
- `POST /prepare-bracket` สร้างสายและเปลี่ยนเป็น `READY`
- `POST /start` สร้างสายหากยังไม่มี แล้วเปลี่ยนเป็น `LIVE`
- บันทึกคะแนนหรือผล Ranking ได้ขณะ `LIVE`
- เปลี่ยนเป็น `COMPLETED` ได้เมื่อผลครบ
- เปลี่ยนเป็น `ARCHIVED` ได้หลังจากจบการแข่งขัน
- `POST /reset-bracket` ล้างคู่ คะแนน ผล Ranking และอันดับ แล้วกลับเป็น `READY`

สามารถใช้ Resource สถานะ:

```bash
curl -X PATCH "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/status" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"LIVE"}'
```

ค่าเป้าหมายที่รับคือ `LIVE`, `COMPLETED` และ `ARCHIVED` โดย Server ตรวจสอบลำดับสถานะให้

## 5. Tournament API

### 5.1 รายการการแข่งขัน

```http
GET /api/tournaments
```

Query parameters:

| Parameter | รายละเอียด |
|---|---|
| `status` | กรอง `DRAFT`, `READY`, `LIVE`, `COMPLETED`, `ARCHIVED` |
| `format` | กรองรูปแบบการแข่งขัน |
| `structure` | กรอง `STANDARD` หรือ `ADVANCED` |
| `division` | กรอง Division แบบตรงค่า |
| `search` | ค้นจากชื่อการแข่งขันหรือชื่อรายการ |
| `per_page` | จำนวนต่อหน้า 1–100 ค่าเริ่มต้น 20 |

ตัวอย่าง:

```bash
curl "https://YOUR_HOST/api/tournaments?structure=ADVANCED&status=LIVE&search=EasyKids&per_page=20" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 5.2 อ่านการแข่งขันหนึ่งรายการ

```http
GET /api/tournaments/{tournament}
```

Response รวม Tournament, Stages, Groups, Group Assignments, Advancement Rules, Participants, Matches และ Standings

### 5.3 สร้างการแข่งขัน Standard

```http
POST /api/tournaments
```

ตัวอย่าง Double Elimination:

```bash
curl -X POST "https://YOUR_HOST/api/tournaments" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"EasyKids 2026",
    "competition":"Robot Challenge",
    "division":"Junior",
    "structure":"STANDARD",
    "format":"DOUBLE_ELIMINATION",
    "seeding_method":"REGISTRATION_ORDER",
    "grand_final_matches":2,
    "competition_date":"2026-09-20",
    "venue":"Chiang Mai"
  }'
```

ฟิลด์หลัก:

| Field | Required | รายละเอียด |
|---|---:|---|
| `name` | ใช่ | ชื่อการแข่งขัน สูงสุด 200 ตัวอักษร |
| `competition` | ใช่ | ชื่อรายการหรือประเภทการแข่งขัน |
| `division` | ใช่ | รุ่นการแข่งขัน |
| `structure` | ไม่ | ค่าเริ่มต้น `STANDARD` |
| `format` | ใช่ | หนึ่งในรูปแบบจาก `/api/capabilities` |
| `seeding_method` | ใช่ | `RANDOM`, `REGISTRATION_ORDER`, `MANUAL`, `RANKING` |
| `competition_date` | ไม่ | วันที่ ISO 8601 |
| `bracket_schedule_start_time` | ไม่ | เวลา `HH:mm` |
| `bracket_match_duration_minutes` | ไม่ | 1–240 นาที |
| `venue`, `notes` | ไม่ | สถานที่และหมายเหตุ |
| `grand_final_matches` | ไม่ | 1 หรือ 2 สำหรับ Double Elimination |

### 5.4 สร้างการแข่งขัน Ranking

Racing Robot:

```json
{
  "name": "Racing Robot",
  "competition": "EasyKids",
  "division": "Junior",
  "structure": "STANDARD",
  "format": "RANKING",
  "seeding_method": "REGISTRATION_ORDER",
  "ranking_type": "RACING_ROBOT",
  "ranking_attempts": 3
}
```

Drone Mission:

```json
{
  "name": "Drone Mission",
  "competition": "EasyKids",
  "division": "Open",
  "structure": "STANDARD",
  "format": "RANKING",
  "seeding_method": "REGISTRATION_ORDER",
  "ranking_type": "DRONE_MISSION",
  "ranking_attempts": 2
}
```

`ranking_attempts` รับค่า 1–20

### 5.5 สร้างการแข่งขัน Advanced

ตัวอย่าง 4 กลุ่ม กลุ่มละไม่เกิน 8 ทีม เลื่อนกลุ่มละ 2 ทีมเข้า Single Elimination Playoff:

```bash
curl -X POST "https://YOUR_HOST/api/tournaments" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Advanced Cup",
    "competition":"Robot Challenge",
    "division":"Open",
    "structure":"ADVANCED",
    "format":"SINGLE_ELIMINATION",
    "seeding_method":"REGISTRATION_ORDER",
    "advanced_group_count":4,
    "advanced_group_limits":[8,8,8,8],
    "advanced_group_format":"ROUND_ROBIN",
    "advanced_qualifiers_per_group":2,
    "advanced_playoff_format":"SINGLE_ELIMINATION",
    "advanced_third_place":true
  }'
```

ฟิลด์ Advanced:

| Field | ขอบเขต |
|---|---|
| `advanced_group_count` | 1–16 กลุ่ม |
| `advanced_group_limits` | Array ความจุแต่ละกลุ่ม ค่าแต่ละตัว 1–64 |
| `advanced_group_format` | `ROUND_ROBIN`, `SINGLE_ELIMINATION` หรือ `DOUBLE_ELIMINATION` |
| `advanced_qualifiers_per_group` | จำนวนทีมที่เลื่อนจากแต่ละกลุ่ม 1–16 |
| `advanced_playoff_format` | `SINGLE_ELIMINATION` หรือ `DOUBLE_ELIMINATION` |
| `advanced_third_place` | สร้างคู่ชิงอันดับ 3 หรือไม่ |

Server สร้าง Group Stage, Playoff Stage, Groups และกฎ `TOP_N` ให้อัตโนมัติ

สำหรับโครงสร้าง `ADVANCED` ค่า `format` ระดับ Tournament จะถูกปรับให้ตรงกับ `advanced_playoff_format` เพื่อให้การคำนวณผลรวมและ Grand Final ใช้กติกาของ Playoff อย่างถูกต้อง

### 5.6 แก้ไขและลบการแข่งขัน

```http
PUT /api/tournaments/{tournament}
PATCH /api/tournaments/{tournament}
DELETE /api/tournaments/{tournament}
```

- `PUT` ใช้แทนที่ข้อมูลหลักและต้องส่งฟิลด์ Required ให้ครบ
- `PATCH` แก้ไขเฉพาะฟิลด์ที่ส่งมา
- เปลี่ยนโครงสร้าง รูปแบบ Seed หรือ Blueprint ได้เฉพาะ `DRAFT`/`READY`
- เมื่อมี Match Graph แล้ว จะไม่อนุญาตให้เปลี่ยนโครงสร้าง
- การลบ Tournament จะลบข้อมูลที่เกี่ยวข้องทั้งหมด

### 5.7 ลิงก์ผู้ชม

```bash
curl -X PATCH "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/share-link" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"share_slug":"easykids-final-26"}'
```

`share_slug` ใช้อักษรอังกฤษตัวเล็ก ตัวเลข และ `-` ความยาว 4–36 ตัว และต้องไม่ซ้ำ

### 5.8 ลำดับแสดงการแข่งขัน

```bash
curl -X PATCH "https://YOUR_HOST/api/tournaments/display-order" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"order":["TOURNAMENT_UUID_1","TOURNAMENT_UUID_2"]}'
```

ส่ง UUID ที่ต้องการจัดลำดับโดยห้ามซ้ำ รายการที่ไม่ได้ส่งจะคงลำดับเดิม

## 6. Participant และ Member API

### 6.1 รายการและรายละเอียดทีม

```http
GET /api/tournaments/{tournament}/participants
GET /api/tournaments/{tournament}/participants/{participant}
```

รายการรองรับ `status`, `search` และ `per_page` หากไม่ส่ง `per_page` จะคืน Array ทั้งหมดเพื่อรักษาความเข้ากันได้กับ API รุ่นเดิม

### 6.2 เพิ่มหรือแก้ไขทีม

```http
POST /api/tournaments/{tournament}/participants
PUT /api/tournaments/{tournament}/participants/{participant}
PATCH /api/tournaments/{tournament}/participants/{participant}
DELETE /api/tournaments/{tournament}/participants/{participant}
```

ตัวอย่าง body:

```json
{
  "team_name": "EasyKids A",
  "team_code": "EKA-01",
  "school": "EasyKids School",
  "coach_name": "Coach Name",
  "seed_number": 1,
  "status": "ACTIVE"
}
```

สถานะทีมคือ `ACTIVE`, `CHECKED_IN`, `WITHDRAWN`, `DISQUALIFIED` การเพิ่ม/ลบทีมและแก้ Seed ทำได้ก่อนสร้าง Match Graph

### 6.3 เพิ่มหลายทีม

```bash
curl -X POST "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/participants/bulk" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"participants":[{"team_name":"Team A"},{"team_name":"Team B"}]}'
```

เพิ่มได้สูงสุด 1,000 ทีมต่อ Request

ลบทุกทีม:

```http
DELETE /api/tournaments/{tournament}/participants
```

### 6.4 นำเข้า CSV

```bash
curl -X POST "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/participants/import" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -F "csv_file=@participants.csv"
```

ไฟล์สูงสุด 5 MB และข้อมูลสูงสุด 1,000 แถว Response แจ้งจำนวน `imported`, `skipped` และรายการ `errors`

### 6.5 สมาชิกทีม

```http
GET    /api/tournaments/{tournament}/participants/{participant}/members
POST   /api/tournaments/{tournament}/participants/{participant}/members
GET    /api/tournaments/{tournament}/participants/{participant}/members/{member}
PUT    /api/tournaments/{tournament}/participants/{participant}/members/{member}
PATCH  /api/tournaments/{tournament}/participants/{participant}/members/{member}
DELETE /api/tournaments/{tournament}/participants/{participant}/members/{member}
```

Body:

```json
{
  "name": "Student Name",
  "role_name": "Driver"
}
```

## 7. Stage, Group และ Advancement API

Resource เหล่านี้สร้างจาก Blueprint ของ Tournament และอ่านได้ผ่าน:

```http
GET /api/tournaments/{tournament}/stages
GET /api/tournaments/{tournament}/stages/{stage}
GET /api/tournaments/{tournament}/groups
GET /api/tournaments/{tournament}/groups/{group}
GET /api/tournaments/{tournament}/advancement-rules
GET /api/tournaments/{tournament}/advancement-rules/{rule}
```

### 7.1 จัดทีมลงกลุ่ม

อ่านการจัดกลุ่ม:

```http
GET /api/tournaments/{tournament}/group-assignments
```

แทนที่การจัดกลุ่มทั้งหมด:

```bash
curl -X PUT "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/group-assignments" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "assignments": {
      "PARTICIPANT_UUID_1": "GROUP_UUID_A",
      "PARTICIPANT_UUID_2": "GROUP_UUID_B"
    }
  }'
```

ต้องส่งผู้เข้าแข่งขันสถานะ `ACTIVE` และ `CHECKED_IN` ให้ครบทุกคน และจำนวนทีมต้องไม่เกิน `team_limit`

สุ่มลงกลุ่มอัตโนมัติ:

```http
POST /api/tournaments/{tournament}/group-assignments/randomize
```

แก้ไขการจัดกลุ่มได้ก่อนสร้าง Match Graph เท่านั้น

### 7.2 อันดับภายในกลุ่ม

```http
GET /api/tournaments/{tournament}/groups/{group}/standings
```

คืน `rank_number`, `played`, `wins`, `draws`, `losses`, `score_for`, `score_against`, `points` และข้อมูลทีม โดยใช้หลักเดียวกับการเลือกทีมเข้า Playoff

## 8. Bracket และ Match API

### 8.1 เตรียม เริ่ม และ Reset สาย

```http
POST /api/tournaments/{tournament}/randomize-participants
POST /api/tournaments/{tournament}/prepare-bracket
POST /api/tournaments/{tournament}/start
POST /api/tournaments/{tournament}/reset-bracket
```

สำหรับ Advanced ระบบจะสร้าง Playoff อัตโนมัติเมื่อผลรอบกลุ่มครบ หรือเรียก Endpoint แบบ Idempotent ได้:

```http
POST /api/tournaments/{tournament}/playoff
```

### 8.2 รายการคู่แข่งขัน

```http
GET /api/tournaments/{tournament}/matches
GET /api/tournaments/{tournament}/matches/{match}
```

ตัวกรองรายการ:

| Parameter | รายละเอียด |
|---|---|
| `stage_id` | เฉพาะ Stage |
| `group_id` | เฉพาะกลุ่ม |
| `status` | สถานะ Match |
| `bracket_type` | `WINNERS`, `LOSERS`, `GRAND_FINAL`, `ROUND_ROBIN`, `RANKING` |
| `round` | หมายเลขรอบ |
| `per_page` | แบ่งหน้า 1–100; ไม่ส่งแล้วคืน Array ทั้งหมด |

### 8.3 เลือกคู่ที่กำลังแข่งขัน

```bash
curl -X PATCH "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/matches/MATCH_UUID/status" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"LIVE"}'
```

คู่ต้องอยู่สถานะ `READY` และมีผู้เข้าแข่งขันครบ ระบบจะเปลี่ยนคู่ `LIVE` เดิมกลับเป็น `READY` เพื่อให้มีคู่ปัจจุบันเพียงคู่เดียว

`POST /matches/{match}/progress` ยังใช้ได้เพื่อรองรับ Client รุ่นเดิม

### 8.4 บันทึกหรือแก้ไขคะแนน Match

```bash
curl -X PUT "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/matches/MATCH_UUID/result" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"score_a":3,"score_b":1}'
```

- `score_a` และ `score_b` ต้องเป็นเลขไม่ติดลบ
- Round Robin ยอมรับผลเสมอ
- Single/Double Elimination ไม่ยอมรับผลเสมอ
- Server คำนวณผู้ชนะ ผู้แพ้ Standings และส่งทีมไปคู่ถัดไปให้อัตโนมัติ
- ส่ง `PUT` ค่าเดิมซ้ำได้อย่างปลอดภัย
- แก้คะแนนคู่ที่จบแล้วได้ขณะ Tournament เป็น `LIVE`
- หากการแก้ไขทำให้ผู้ชนะเปลี่ยน Server จะปรับคู่ปลายทางที่ยังไม่เริ่ม
- หากคู่ปลายทางเริ่มแล้ว Server จะปฏิเสธการเปลี่ยนผู้ชนะ
- สำหรับ Advanced จะใช้ `format` ของ Stage ปัจจุบัน เช่น รอบกลุ่มยอมเสมอ แต่ Elimination Playoff ไม่ยอมเสมอ

`POST /matches/{match}/result` ยังใช้ได้เพื่อรองรับ Client รุ่นเดิม แต่ Integration ใหม่ควรใช้ `PUT`

## 9. Ranking Attempt API

### 9.1 อ่านผล Ranking

```http
GET /api/tournaments/{tournament}/participants/{participant}/attempts
GET /api/tournaments/{tournament}/participants/{participant}/attempts/{number}
```

### 9.2 Racing Robot

```bash
curl -X PUT "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/participants/PARTICIPANT_UUID/attempts/1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"attempt_time":12.35,"is_valid":true}'
```

รองรับชื่อเดิม `attempt_value` แทน `attempt_time` เวลาใช้ทศนิยมไม่เกิน 2 ตำแหน่ง

### 9.3 Drone Mission

```bash
curl -X PUT "https://YOUR_HOST/api/tournaments/TOURNAMENT_UUID/participants/PARTICIPANT_UUID/attempts/1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "manual_score":40,
    "auto_score":45,
    "attempt_time":72.50,
    "is_valid":true
  }'
```

- `manual_score` และ `auto_score` อยู่ระหว่าง 0–50
- คะแนนรวมคำนวณจาก Manual + Automatic
- เรียงคะแนนรวมมากกว่าเป็นอันดับดีกว่า หากเท่ากันใช้เวลาน้อยกว่า
- `is_valid` มีค่าเริ่มต้น `true`
- หมายเลข Attempt ต้องไม่เกินจำนวนที่กำหนดใน Tournament

สร้าง/แก้โดยส่งหมายเลขใน body ก็ได้:

```http
POST /api/tournaments/{tournament}/participants/{participant}/attempts
```

พร้อม `attempt_number` ใน JSON

ลบ Attempt และคำนวณอันดับใหม่:

```http
DELETE /api/tournaments/{tournament}/participants/{participant}/attempts/{number}
```

การเขียนและลบ Attempt ทำได้ขณะ Tournament Ranking เป็น `LIVE`

## 10. Standings และ Live State

```http
GET /api/tournaments/{tournament}/standings
GET /api/tournaments/{tournament}/standings/{participant}
```

เพิ่ม `per_page=1..100` เพื่อแบ่งหน้า หากไม่ส่งจะคืน Array ทั้งหมด

ตรวจสอบว่าข้อมูลเปลี่ยนหรือยังโดยไม่ดาวน์โหลดทุก Resource:

```http
GET /api/tournaments/{tournament}/live-state
```

Response มี SHA-256 `version`, Tournament `status` และ `synced_at` เมื่อ `version` เปลี่ยน Client จึงค่อยโหลด Matches หรือ Standings ใหม่

## 11. HTTP Status Code

| Code | ความหมาย |
|---:|---|
| `200` | อ่าน แก้ไข หรือลบสำเร็จ |
| `201` | สร้าง Resource สำเร็จ |
| `401` | ไม่มี Bearer Token หรือ Token ไม่ถูกต้อง |
| `403` | Token เป็นของบัญชีที่ไม่ใช่ผู้ดูแล |
| `404` | ไม่พบ Resource หรือ Resource ไม่ได้อยู่ใต้ Tournament/Participant ที่ระบุ |
| `422` | Request ไม่ผ่าน Validation หรือสถานะปัจจุบันไม่อนุญาต Operation |
| `429` | เกิน Rate Limit 60 Request ต่อนาที |

## 12. Checklist สำหรับ Integration

1. เรียก `/api/capabilities` และตรวจสอบค่าที่ Server รองรับ
2. สร้าง Tournament และเก็บ UUID จาก `data.id`
3. เพิ่มหรือนำเข้า Participants
4. ถ้าเป็น Advanced ให้อ่าน Groups แล้วจัดทุกทีมลงกลุ่ม
5. เรียก `/prepare-bracket` หากต้องการตรวจสายก่อน หรือเรียก `/start` เพื่อเริ่มทันที
6. ใช้ Match Result สำหรับการแข่งขันแบบพบกัน/แพ้คัดออก หรือ Ranking Attempt สำหรับ `RANKING`
7. อ่าน Standings และใช้ `/live-state` เพื่อลดการ Poll ข้อมูลขนาดใหญ่
8. เปลี่ยนเป็น `COMPLETED` เมื่อผลครบ แล้วเปลี่ยนเป็น `ARCHIVED` เมื่อเลิกใช้งาน

## 13. ตาราง Endpoint ฉบับย่อ

| กลุ่ม | Endpoint หลัก |
|---|---|
| Discovery | `GET /health`, `GET /capabilities` |
| Tournaments | `GET/POST /tournaments`, `GET/PUT/PATCH/DELETE /tournaments/{id}` |
| Lifecycle | `POST /randomize-participants`, `/prepare-bracket`, `/start`, `/playoff`, `/reset-bracket`, `/complete`, `/archive`; `PATCH /status` |
| Participants | `GET/POST/DELETE /participants`, `POST /participants/bulk`, `POST /participants/import`, `GET/PUT/PATCH/DELETE /participants/{participant}` |
| Members | `GET/POST /members`, `GET/PUT/PATCH/DELETE /members/{member}` |
| Advanced | `GET /stages`, `/groups`, `/advancement-rules`, `GET/PUT /group-assignments`, `POST /group-assignments/randomize` |
| Matches | `GET /matches`, `GET /matches/{match}`, `PATCH /matches/{match}/status`, `PUT /matches/{match}/result` |
| Ranking | `GET/POST /attempts`, `GET/PUT/DELETE /attempts/{number}` |
| Results | `GET /standings`, `GET /standings/{participant}`, `GET /groups/{group}/standings`, `GET /live-state` |
