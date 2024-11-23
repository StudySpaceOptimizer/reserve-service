### Reservation API Documentation

#### 1. Reserve Seat
- Endpoint: `POST /reservations`
- Description: Create a new reservation for a specific seat and time range.
- Request Body:
  ```json
  {
    "seatId": "integer",
    "beginTime": "string (ISO 8601 DateTime format)",
    "endTime": "string (ISO 8601 DateTime format)"
  }
  ```
- Response:
  - 201 Created:
    ```json
    {
      "message": "Reservation successful",
      "id": "integer"
    }
    ```
  - 400 Bad Request (examples):
    ```json
    { "error": "Reservation time must be in the future" }
    { "error": "Seat is already reserved during this time" }
    ```

#### 2. Get Reservations
- Endpoint: `GET /reservations`
- Description: Retrieve a paginated list of all reservations.
- Query Parameters:
  - `pageSize` (optional): integer, default is 10, range 1–100
  - `pageOffset` (optional): integer, default is 0
- Response:
  - 200 OK:
    ```json
    {
      "total": "integer",
      "data": [
        {
          "reservationId": "integer",
          "seatId": "integer",
          "beginTime": "string (ISO 8601 DateTime format)",
          "endTime": "string (ISO 8601 DateTime format)",
          "userEmail": "string"
        }
      ]
    }
    ```

#### 3. Get My Reservations
- Endpoint: `GET /reservations/me`
- Description: Retrieve a paginated list of reservations created by the current user.
- Query Parameters:
  - `pageSize` (optional): integer, default is 10, range 1–100
  - `pageOffset` (optional): integer, default is 0
- Response:
  - 200 OK:
    ```json
    {
      "total": "integer",
      "data": [
        {
          "reservationId": "integer",
          "seatId": "integer",
          "beginTime": "string (ISO 8601 DateTime format)",
          "endTime": "string (ISO 8601 DateTime format)"
        }
      ]
    }
    ```

#### 4. Delete Reservation
- Endpoint: `DELETE /reservations/{id}`
- Description: Delete a reservation by its ID. Only the creator of the reservation or an admin can delete it.
- Path Parameters:
  - `id`: integer, ID of the reservation to delete
- Response:
  - 204 No Content:
    ```json
    { "message": "Reservation deleted successfully" }
    ```
  - 403 Forbidden:
    ```json
    { "error": "You are not authorized to delete this reservation" }
    ```
  - 404 Not Found:
    ```json
    { "error": "Reservation not found" }
    ```

---

### Notes:
1. DateTime Format: All time inputs and outputs follow ISO 8601 format (e.g., `"2024-11-24T10:00:00Z"`).
2. Authentication:
   - APIs like `GET /reservations/me` and `DELETE /reservations/{id}` require user authentication to determine `user.email` and `user.role`.
   - Admins have elevated permissions for deletion.
3. Validation Rules:
   - Reservations must be within business hours (09:00–21:00).
   - Reservations cannot overlap with existing ones for the same seat.
   - Users can only make one reservation per day.