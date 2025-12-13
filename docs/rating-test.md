# Rating Feature Test Checklist

Manual test checklist for the movie rating functionality.

## Prerequisites

- App running at http://localhost:8080
- User logged in
- At least one movie in the library (add via search if needed)

## Test Steps

### 1. Navigate to Movie Detail

```
GET http://localhost:8080/movie/1
```

Expected: Page loads with 200, shows movie details and rating form.

### 2. Hover Preview (Desktop)

- [ ] Hover over popcorn icons
- [ ] Icons fill up to hovered position (yellow)
- [ ] Moving mouse away restores previous selection (or empty if none)

### 3. Click Selection

- [ ] Click on 5th popcorn
- [ ] Icons 1-5 turn yellow, 6-7 stay gray
- [ ] Hidden input value is "5"
- [ ] Click same popcorn again to toggle off (all gray, value "0")

### 4. Submit Rating

- [ ] Select rating (e.g., 5 popcorns)
- [ ] Optionally add comment
- [ ] Click "Submit Rating"
- [ ] Expected: 302 redirect to `/movie/1#ratings`
- [ ] Page reloads showing your rating

### 5. Update Rating

- [ ] Change rating to different value
- [ ] Update comment
- [ ] Submit
- [ ] Expected: Rating updates, `updated_at` changes

### 6. Verify Database

```bash
docker exec -it pathary-mysql mysql -upathary_user -ppathary_pass_123! pathary \
  -e "SELECT * FROM movie_user_rating WHERE movie_id = 1;"
```

Expected columns:
- `movie_id`: 1
- `user_id`: your user ID
- `rating_popcorn`: 1-7 or NULL
- `comment`: your comment or NULL
- `updated_at`: recent timestamp

## Curl Test (No CSRF Required)

```bash
# Get a session cookie first by logging in via browser, then:
curl -i -X POST http://localhost:8080/movie/1/rate \
  -b "id=YOUR_AUTH_COOKIE" \
  -d "rating_popcorn=5" \
  -d "comment=Great movie!"
```

Expected response:
```
HTTP/1.1 303 See Other
Location: /movie/1#ratings
```

## Keyboard Navigation

- [ ] Tab to first popcorn button
- [ ] Press Arrow Right/Up to increase rating
- [ ] Press Arrow Left/Down to decrease rating
- [ ] Press Enter/Space on focused button to select

## Mobile (Touch)

- [ ] Tap popcorn icons to select
- [ ] No hover state needed
- [ ] Submit works normally

## Edge Cases

- [ ] Submit with rating 0 (unrated): Should save NULL for rating_popcorn
- [ ] Submit with only comment (no rating): Should save NULL for rating_popcorn
- [ ] Very long comment: Should truncate or handle gracefully
- [ ] Special characters in comment: Should be escaped properly
