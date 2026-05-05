#!/bin/bash
# ================================================
# Calibehr LMS — Full API Test Script
# Usage: bash test_all_apis.sh
# ================================================

BASE="http://127.0.0.1:8000"
TOKEN="36|J3jubLyVzkJ2b5v0vn1ebCKXCy3QjxHjEEFsZOrI75e3eb63"

PASS=0
FAIL=0

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

get() {
  curl -s -X GET "$BASE$1" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json"
}

post() {
  curl -s -X POST "$BASE$1" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$2"
}

check() {
  local name="$1"
  local resp="$2"
  local code=$(echo "$resp" | python3 -c "import sys,json; print(json.load(sys.stdin).get('code','?'))" 2>/dev/null)
  local msg=$(echo "$resp"  | python3 -c "import sys,json; print(json.load(sys.stdin).get('message','?'))" 2>/dev/null)
  local data=$(echo "$resp" | python3 -c "
import sys,json
d=json.load(sys.stdin).get('data')
if d is None: print('null')
elif isinstance(d,list): print('array['+str(len(d))+']')
elif isinstance(d,dict): print('object:'+str(list(d.keys()))[:50])
else: print(str(d)[:60])
" 2>/dev/null)

  if [ "$code" = "1" ]; then
    echo -e "${GREEN}✅ PASS${NC} $name | data=$data | $msg"
    ((PASS++))
  else
    echo -e "${RED}❌ FAIL${NC} $name | code=$code | $msg"
    echo -e "        RAW: $(echo $resp | head -c 300)"
    ((FAIL++))
  fi
}

echo ""
echo -e "${YELLOW}========================================"
echo "   CALIBEHR LMS — FULL API TEST"
echo -e "========================================${NC}"

# ─── AUTH ────────────────────────────────────
echo -e "\n${YELLOW}--- AUTH ---${NC}"
check "GET  /auth/me"              "$(get /api/auth/me)"
check "POST /auth/change-password" "$(post /api/auth/change-password '{"current_password":"test","new_password":"test123","new_password_confirmation":"test123"}')"
check "GET  /auth/account-info"    "$(get /api/auth/account-info)"

# ─── CATEGORIES ──────────────────────────────
echo -e "\n${YELLOW}--- CATEGORIES ---${NC}"
check "GET  getCategories"    "$(get  /api/Webservice/getCategories)"
check "GET  getCategoryDetails" "$(get /api/Webservice/getCategoryDetails?id=1)"
check "POST addCategory"      "$(post /api/Webservice/addCategory      '{"name":"api-test-cat"}')"
check "POST updateCategory"   "$(post /api/Webservice/updateCategory   '{"id":1,"name":"cat updated"}')"
check "POST disableCategory"  "$(post /api/Webservice/disableCategory  '{"id":1}')"
check "POST enableCategory"   "$(post /api/Webservice/enableCategory   '{"id":1}')"

# ─── COURSES ─────────────────────────────────
echo -e "\n${YELLOW}--- COURSES ---${NC}"
check "GET  getCourseList"         "$(get /api/Webservice/getCourseList)"
check "GET  getCatalogCourseList"  "$(get /api/Webservice/getCatalogCourseList)"
check "GET  getCourseDetails"      "$(get /api/Webservice/getCourseDetails?courseID=1)"
check "GET  getCourseDetailsWS"    "$(get /api/Webservice/getCourseDetailsWS?courseID=1)"
check "GET  getCourseType"         "$(get /api/Webservice/getCourseType)"
check "GET  getFeaturedCourseList" "$(get /api/Webservice/getFeaturedCourseList)"
check "GET  getFeaturedListMng"    "$(get /api/Webservice/getFeaturedListMng)"
check "GET  getUnfeaturedList"     "$(get /api/Webservice/getUnfeaturedList)"
check "POST addCourse"             "$(post /api/Webservice/addCourse '{"name":"API Test Course","category_id":1,"type":1}')"
check "POST updateCourseDetail"    "$(post /api/Webservice/updateCourseDetail '{"courseID":1,"name":"Updated Course"}')"
check "POST visibilityStatus"      "$(post /api/Webservice/visibilityStatus '{"courseID":1,"visibility":0}')"
check "POST makeCourseLive"        "$(post /api/Webservice/makeCourseLive '{"courseID":1}')"
check "POST disableCourse"         "$(post /api/Webservice/disableCourse '{"courseID":1}')"

# ─── FEATURED ────────────────────────────────
echo -e "\n${YELLOW}--- FEATURED ---${NC}"
check "POST addToFeaturedCourse"   "$(post /api/Webservice/addToFeaturedCourse '{"courseID":2}')"
check "POST removeFromFeature"     "$(post /api/Webservice/removeFromFeature '{"courseID":2}')"

# ─── CHAPTERS ────────────────────────────────
echo -e "\n${YELLOW}--- CHAPTERS ---${NC}"
check "GET  getCourseChapterDetails"      "$(get /api/Webservice/getCourseChapterDetails?chapterID=1)"
check "GET  getCourseChapterDetailsAdmin" "$(get /api/Webservice/getCourseChapterDetailsAdmin?chapterID=1)"
check "GET  courseChapterDetailAdmin"     "$(get /api/Webservice/courseChapterDetailAdmin?chapterID=1)"
check "POST addChapter"                  "$(post /api/Webservice/addChapter '{"courseID":1,"name":"Test Chapter"}')"
check "POST updateChapter"               "$(post /api/Webservice/updateChapter '{"chapterID":1,"courseID":1,"name":"Updated Chapter"}')"
check "POST disableChapter"              "$(post /api/Webservice/disableChapter '{"chapterID":1,"courseID":1}')"
check "POST enableChapter"               "$(post /api/Webservice/enableChapter '{"chapterID":1,"courseID":1}')"

# ─── TOPICS ──────────────────────────────────
echo -e "\n${YELLOW}--- TOPICS ---${NC}"
check "GET  courseTopicDetailAdmin" "$(get /api/Webservice/courseTopicDetailAdmin?topicID=1)"
check "GET  courseTopicDetailWS"    "$(get /api/Webservice/courseTopicDetailWS?topicID=1)"
check "POST newVideoTopic"          "$(post /api/Webservice/newVideoTopic '{"courseID":1,"chapterID":1,"name":"Test Video"}')"
check "POST newPDFTopic"            "$(post /api/Webservice/newPDFTopic '{"courseID":1,"chapterID":1,"name":"Test PDF"}')"
check "POST newTestTopic"           "$(post /api/Webservice/newTestTopic '{"courseID":1,"chapterID":1,"name":"Test Quiz Topic"}')"
check "POST updateVideoTopic"       "$(post /api/Webservice/updateVideoTopic '{"topicID":1,"courseID":1,"name":"Updated Video"}')"
check "POST removeTopic"            "$(post /api/Webservice/removeTopic '{"topicID":1,"chapterID":1,"courseID":1}')"

# ─── RESOURCE LINKS ──────────────────────────
echo -e "\n${YELLOW}--- RESOURCE LINKS ---${NC}"
check "GET  getCourseChapterResourceList"   "$(get /api/Webservice/getCourseChapterResourceList?topicID=1)"
check "GET  getCourseChapterResourceDetail" "$(get /api/Webservice/getCourseChapterResourceDetail?linkID=1)"
check "POST addResourceLinks"  "$(post /api/Webservice/addResourceLinks '{"courseID":1,"chapterID":1,"topicID":1,"links":[{"name":"Google","link":"https://google.com","description":"test"}]}')"
check "POST removeResourseLink" "$(post /api/Webservice/removeResourseLink '{"linkID":1}')"

# ─── TEST QUESTIONS ───────────────────────────
echo -e "\n${YELLOW}--- TEST QUESTIONS ---${NC}"
check "GET  getQuestionsListWS" "$(get /api/Webservice/getQuestionsListWS?topicID=1)"
check "GET  getTestDetail"      "$(get /api/Webservice/getTestDetail?topicID=1)"
check "GET  getTestQuestion"    "$(get /api/Webservice/getTestQuestion?questionID=1)"
check "POST addTestQuestion"    "$(post /api/Webservice/addTestQuestion '{"courseID":1,"chapterID":1,"topicID":1,"question_text":"Test Q?"}')"
check "POST addTestQuestionOptions" "$(post /api/Webservice/addTestQuestionOptions '{"questionID":1,"options":[{"option_text":"Yes","answer":1},{"option_text":"No","answer":0}]}')"
check "POST sendTestQuestion"   "$(post /api/Webservice/sendTestQuestion '{"questionID":1}')"
check "POST deleteQuestionOption" "$(post /api/Webservice/deleteQuestionOption '{"optionID":1}')"
check "POST deleteQuestion"     "$(post /api/Webservice/deleteQuestion '{"questionID":1}')"
check "GET  getResultUserWS"    "$(get /api/Webservice/getResultUserWS?courseID=1&topicID=1)"
check "GET  getResultDetails"   "$(get /api/Webservice/getResultDetails?answerID=1)"
check "GET  getResultSummary"   "$(get /api/Webservice/getResultSummary?courseID=1&topicID=1)"

# ─── LEARNERS ────────────────────────────────
echo -e "\n${YELLOW}--- LEARNERS ---${NC}"
check "GET  assignLearnersModalList" "$(get /api/Webservice/assignLearnersModalList?courseID=1)"
check "GET  getLearnersBoxList"      "$(get /api/Webservice/getLearnersBoxList?courseID=1)"
check "POST enrollSelf"              "$(post /api/Webservice/enrollSelf '{"courseID":2}')"
check "POST assignLearnersCourse"    "$(post /api/Webservice/assignLearnersCourse '{"courseID":1,"learners":[1,2]}')"
check "POST markAsComplete"          "$(post /api/Webservice/markAsComplete '{"courseID":1,"topicID":1}')"
check "POST updateTopicTimeWS"       "$(post /api/Webservice/updateTopicTimeWS '{"courseID":1,"topicID":1,"timeSpent":60}')"

# ─── MY COURSES ──────────────────────────────
echo -e "\n${YELLOW}--- MY COURSES ---${NC}"
check "GET  getMycoursesInprogress"  "$(get /api/Webservice/getMycoursesInprogress)"

# ─── FEEDBACK ────────────────────────────────
echo -e "\n${YELLOW}--- FEEDBACK ---${NC}"
check "GET  getAllFeedbackWS"        "$(get /api/Webservice/getAllFeedbackWS?courseID=1)"
check "GET  getFeedbackQuestionsWS"  "$(get /api/Webservice/getFeedbackQuestionsWS?courseID=1)"
check "GET  getFeedbackQuestion"     "$(get /api/Webservice/getFeedbackQuestion?questionID=1)"
check "POST addFeedBackWS"           "$(post /api/Webservice/addFeedBackWS '{"courseID":1,"star":4,"comment":"Good"}')"
check "POST addFeedbackQuestion"     "$(post /api/Webservice/addFeedbackQuestion '{"courseID":1,"question_text":"Rate the content?"}')"
check "POST deleteFeedbackQuestion"  "$(post /api/Webservice/deleteFeedbackQuestion '{"questionID":1}')"
check "POST deleteFeedBackWS"        "$(post /api/Webservice/deleteFeedBackWS '{"feedbackID":1}')"

# ─── CHARTS ──────────────────────────────────
echo -e "\n${YELLOW}--- CHARTS ---${NC}"
check "GET  getOverViewCharts"     "$(get /api/Webservice/getOverViewCharts?courseID=1)"
check "GET  getOverPassViewCharts" "$(get /api/Webservice/getOverPassViewCharts?courseID=1)"

# ─── LEADERBOARD ─────────────────────────────
echo -e "\n${YELLOW}--- LEADERBOARD ---${NC}"
check "GET  getLeaderBoardWS"       "$(get /api/Webservice/getLeaderBoardWS)"
check "GET  getLeaderBoardWeb"      "$(get /api/Webservice/getLeaderBoardWeb)"
check "GET  getDepartmentLeaderBoardWS" "$(get /api/Webservice/getDepartmentLeaderBoardWS)"
check "GET  leaderboard/department"    "$(get /api/leaderboard/department)"

# ─── WISHLIST ────────────────────────────────
echo -e "\n${YELLOW}--- WISHLIST ---${NC}"
check "GET  getWishlistWS"    "$(get  /api/Webservice/getWishlistWS)"
check "POST anrToWishlistWS"  "$(post /api/Webservice/anrToWishlistWS '{"courseID":2}')"

# ─── APP VERSION ─────────────────────────────
echo -e "\n${YELLOW}--- APP VERSION ---${NC}"
check "GET  getAppVersion"  "$(get  /api/Webservice/getAppVersion?device=1)"
check "POST addAppversion"  "$(post /api/Webservice/addAppversion '{"device":1,"app_version":"2.0.0"}')"

# ─── INTERVIEW ───────────────────────────────
echo -e "\n${YELLOW}--- INTERVIEW ---${NC}"
check "GET  getAllInterviewList"      "$(get /api/Webservices/getAllInterviewList)"
check "GET  getInterviewDetails"     "$(get /api/Webservice/getInterviewDetails?interviewID=1)"
check "GET  getInterviewQuestions"   "$(get /api/Webservice/getInterviewQuestions?interviewID=1)"
check "GET  getInterviewInvitedList" "$(get /api/Webservice/getInterviewInvitedList?interviewID=1)"
check "GET  getInterviewWS"          "$(get /api/Webservice/getInterviewWS?uniqueID=test)"
check "GET  getOverViewChartsInterview" "$(get /api/Webservice/getOverViewChartsInterview?interviewID=1)"
check "POST addInterview"            "$(post /api/Webservice/addInterview '{"name":"Test Interview"}')"
check "POST updateInterviewDetail"   "$(post /api/Webservice/updateInterviewDetail '{"interviewID":1,"name":"Updated Interview"}')"
check "POST addInterviewQuestion"    "$(post /api/Webservice/addInterviewQuestion '{"interviewID":1,"question_text":"Tell me about yourself","question_time":2}')"
check "GET  getInterviewQuestionDetail" "$(get /api/Webservice/getInterviewQuestionDetail?questionID=1)"
check "POST updateInterviewQuestion" "$(post /api/Webservice/updateInterviewQuestion '{"questionID":1,"interviewID":1,"question_text":"Updated Q"}')"
check "POST deleteInterviewQuestion" "$(post /api/Webservice/deleteInterviewQuestion '{"questionID":1}')"

# ─── SETTINGS ────────────────────────────────
echo -e "\n${YELLOW}--- SETTINGS ---${NC}"
check "GET  getAllUserList"   "$(get /api/Webservices/getAllUserList)"
check "GET  getUserDetailList" "$(get /api/Webservices/getUserDetailList?userID=1)"
check "GET  getAllRoles"      "$(get /api/Webservices/getAllRoles)"

# ─── REPORTS ─────────────────────────────────
echo -e "\n${YELLOW}--- REPORTS ---${NC}"
check "GET  reports/course"   "$(get /api/reports/course)"
check "GET  reports/quiz"     "$(get /api/reports/quiz)"
check "GET  reports/learner"  "$(get /api/reports/learner)"
check "GET  reports/feedback" "$(get /api/reports/feedback)"

# ─── NOTIFICATIONS ───────────────────────────
echo -e "\n${YELLOW}--- NOTIFICATIONS ---${NC}"
check "POST register-token"   "$(post /api/notifications/register-token '{"token":"test-fcm-token","device_type":"android"}')"

# ─── SUMMARY ─────────────────────────────────
echo ""
echo -e "${YELLOW}========================================"
echo "              SUMMARY"
echo -e "========================================${NC}"
echo -e "  ${GREEN}✅ PASS: $PASS${NC}"
echo -e "  ${RED}❌ FAIL: $FAIL${NC}"
echo -e "  Total: $((PASS+FAIL))"
echo ""
