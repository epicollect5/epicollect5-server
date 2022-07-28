import { combineReducers } from 'redux';
import entriesTotalReducer from 'reducers/entriesTotalReducer';
import entriesTodayReducer from 'reducers/entriesTodayReducer';
import entriesWeekReducer from 'reducers/entriesWeekReducer';
import entriesMonthReducer from 'reducers/entriesMonthReducer';
import entriesYearReducer from 'reducers/entriesYearReducer';
import entriesByMonthReducer from 'reducers/entriesByMonthReducer';
import entriesPlatformReducer from 'reducers/entriesPlatformReducer';

import projectsTotalReducer from 'reducers/projectsTotalReducer';
import projectsTodayReducer from 'reducers/projectsTodayReducer';
import projectsWeekReducer from 'reducers/projectsWeekReducer';
import projectsMonthReducer from 'reducers/projectsMonthReducer';
import projectsByMonthReducer from 'reducers/projectsByMonthReducer';
import projectsByThresholdReducer from 'reducers/projectsByThresholdReducer';
import projectsYearReducer from 'reducers/projectsYearReducer';

import usersStatsReducer from 'reducers/usersStatsReducer';

const rootReducer = combineReducers({
    entriesTotalReducer,
    entriesTodayReducer,
    entriesWeekReducer,
    entriesMonthReducer,
    entriesYearReducer,
    entriesByMonthReducer,
    entriesPlatformReducer,
    projectsTotalReducer,
    projectsTodayReducer,
    projectsWeekReducer,
    projectsMonthReducer,
    projectsByMonthReducer,
    projectsByThresholdReducer,
    projectsYearReducer,
    usersStatsReducer
});

export default rootReducer;

