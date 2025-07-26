package com.turning_leaf_technologies.cloud_library;

import org.aspen_discovery.grouping.RecordGroupingProcessor;
import org.aspen_discovery.grouping.RemoveRecordFromWorkResult;
import org.aspen_discovery.reindexer.GroupedWorkIndexer;
import org.apache.logging.log4j.Logger;
import org.xml.sax.helpers.DefaultHandler;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.zip.CRC32;

class CloudLibraryEventHandler extends DefaultHandler {
	private final CloudLibraryExporter exporter;
	private PreparedStatement updateCloudLibraryAvailabilityStmt;
	private PreparedStatement getExistingCloudLibraryAvailabilityStmt;
	private PreparedStatement deleteCloudLibraryAvailabilityStmt;
	private PreparedStatement cloudLibraryTitleHasAvailabilityStmt;
	private PreparedStatement deleteCloudLibraryItemStmt;

	private final boolean doFullReload;
	private final RecordGroupingProcessor recordGroupingProcessor;
	private final GroupedWorkIndexer indexer;
	private final Logger logger;
	private final CloudLibraryExtractLogEntry logEntry;
	private final long startTimeForLogging;
	private String nodeContents = "";

	private String currentItemId = "";
	private String currentEventType = "";
	private String lastEventDateTimeInUTC = "";

	private static final CRC32 checksumCalculator = new CRC32();

	CloudLibraryEventHandler(CloudLibraryExporter exporter, boolean doFullReload, Long startTimeForLogging, Connection aspenConn, RecordGroupingProcessor recordGroupingProcessor, GroupedWorkIndexer groupedWorkIndexer, CloudLibraryExtractLogEntry logEntry, Logger logger) {
		this.exporter = exporter;
		this.recordGroupingProcessor = recordGroupingProcessor;
		this.indexer = groupedWorkIndexer;
		this.logEntry = logEntry;
		this.logger = logger;
		this.doFullReload = doFullReload;
		this.startTimeForLogging = startTimeForLogging;

		try {
			getExistingCloudLibraryAvailabilityStmt = aspenConn.prepareStatement("SELECT id, rawChecksum from cloud_library_availability WHERE cloudLibraryId = ? and settingId = " + exporter.getSettingsId());
			updateCloudLibraryAvailabilityStmt = aspenConn.prepareStatement(
					"INSERT INTO cloud_library_availability " +
							"(cloudLibraryId, settingId, totalCopies, sharedCopies, totalLoanCopies, totalHoldCopies, sharedLoanCopies, rawChecksum, rawResponse, lastChange) " +
							"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) " +
							"ON DUPLICATE KEY UPDATE totalCopies = VALUES(totalCopies), sharedCopies = VALUES(sharedCopies), " +
							"totalLoanCopies = VALUES(totalLoanCopies), totalHoldCopies = VALUES(totalHoldCopies), sharedLoanCopies = VALUES(sharedLoanCopies), " +
							"rawChecksum = VALUES(rawChecksum), rawResponse = VALUES(rawResponse), lastChange = VALUES(lastChange)");

			deleteCloudLibraryAvailabilityStmt = aspenConn.prepareStatement("DELETE FROM cloud_library_availability where cloudLibraryId = ? and settingId = ?");
			cloudLibraryTitleHasAvailabilityStmt = aspenConn.prepareStatement("SELECT count(*) as numAvailability FROM cloud_library_availability where cloudLibraryId = ?");
			deleteCloudLibraryItemStmt = aspenConn.prepareStatement("UPDATE cloud_library_title SET deleted = 1 where cloudLibraryId = ?");
		} catch (Exception e) {
			logger.error("Error connecting to aspen database", e);
			System.exit(1);
		}
	}

	public void characters(char[] ch, int start, int length) {
		nodeContents += new String(ch, start, length);
	}

	public void endElement(String uri, String localName, String qName) {
		switch (qName) {
			case "ItemId":
				currentItemId = nodeContents.trim();
				break;
			case "EventType":
				currentEventType = nodeContents.trim();
				break;
			case "CloudLibraryEvent":
				// Process the complete event when we reach the end of the event element
				processCloudLibraryEvent(currentItemId, currentEventType);
				// Reset for next event
				currentItemId = "";
				currentEventType = "";
				break;
			case "LastEventDateTimeInUTC":
				if (nodeContents != null && !nodeContents.trim().isEmpty()) {
					lastEventDateTimeInUTC = nodeContents.trim();
					logger.warn("LastEventDateTimeInUTC: " + lastEventDateTimeInUTC);
				} else {
					lastEventDateTimeInUTC = null;
					logger.warn("LastEventDateTimeInUTC is empty, no more events");
				}
				break;
		}
		nodeContents = "";
	}

	public String getLastEventDateTimeInUTC() {
		return lastEventDateTimeInUTC;
	}

	private void processCloudLibraryEvent(String cloudLibraryId, String eventType) {
		if (cloudLibraryId.isEmpty()) {
			logger.warn("Skipping event with empty LibraryId");
			return;
		}
		if ("REMOVED".equals(eventType)) {
			// Delete expired titles
			deleteExpiredTitle(cloudLibraryId);
		} else {
			updateAvailabilityForTitle(cloudLibraryId);
		}
	}

	private void deleteExpiredTitle(String cloudLibraryId) {
		logger.warn("Processing REMOVED event for expired title: " + cloudLibraryId);
		try {
			deleteCloudLibraryAvailabilityStmt.setString(1, cloudLibraryId);
			deleteCloudLibraryAvailabilityStmt.setLong(2, exporter.getSettingsId());
			int deletedRows = deleteCloudLibraryAvailabilityStmt.executeUpdate();

			deleteCloudLibraryAvailabilityStmt.close();

			if (deletedRows > 0) {
				logEntry.incAvailabilityChanges();
				logger.warn("Removed availability for expired title " + cloudLibraryId + " from setting " + exporter.getSettingsId());

				cloudLibraryTitleHasAvailabilityStmt.setString(1, cloudLibraryId);
				ResultSet cloudLibraryTitleHasAvailabilityRS = cloudLibraryTitleHasAvailabilityStmt.executeQuery();
				boolean shouldDeleteTitle = true;
				if (cloudLibraryTitleHasAvailabilityRS.next()) {
					int remainingAvailability = cloudLibraryTitleHasAvailabilityRS.getInt("numAvailability");
					if (remainingAvailability > 0) {
						shouldDeleteTitle = false;
						logger.warn("Title " + cloudLibraryId + " still available in " + remainingAvailability + " other setting(s)");
					}
				}

				if (shouldDeleteTitle) {
					deleteCloudLibraryItemStmt.setString(1, cloudLibraryId);
					deleteCloudLibraryItemStmt.executeUpdate();
					logEntry.incDeleted();
					logger.info("Marked title " + cloudLibraryId + " as deleted - expired from all collections");
					RemoveRecordFromWorkResult result = recordGroupingProcessor.removeRecordFromGroupedWork("cloud_library", cloudLibraryId);
					if (result.reindexWork) {
						indexer.processGroupedWork(result.permanentId);
						logger.warn("Reindexed grouped work " + result.permanentId + " after title expiration");
					} else if (result.deleteWork) {
						indexer.deleteRecord(result.permanentId, result.groupedWorkId);
						logger.warn("Deleted grouped work " + result.permanentId + " - no remaining records");
					}
				} else {
					String groupedWorkId = recordGroupingProcessor.getPermanentIdForRecord("cloud_library", cloudLibraryId);
					if (groupedWorkId != null) {
						indexer.processGroupedWork(groupedWorkId);
						logger.warn("Updated grouped work " + groupedWorkId + " to reflect availability change");
					}
				}
				cloudLibraryTitleHasAvailabilityStmt.close();
				deleteCloudLibraryItemStmt.close();
			} else {
				logger.warn("No availability record found to delete for " + cloudLibraryId + " in setting " + exporter.getSettingsId());
			}
		} catch (SQLException e) {
			logEntry.incErrors("Error processing REMOVED event for title " + cloudLibraryId, e);
		}
	}

	private void updateAvailabilityForTitle(String cloudLibraryId) {
		//Get availability for the title
		CloudLibraryAvailability availability = exporter.loadAvailabilityForRecord(cloudLibraryId);
		if (availability == null) {
			logEntry.addNote("Did not load availability for id " + cloudLibraryId);
			return;
		}

		checksumCalculator.reset();
		String rawAvailabilityResponse = availability.getRawResponse();
		if (rawAvailabilityResponse == null) {
			rawAvailabilityResponse = "";
		}
		checksumCalculator.update(rawAvailabilityResponse.getBytes());
		long availabilityChecksum = checksumCalculator.getValue();
		boolean availabilityChanged = false;
		try {
			getExistingCloudLibraryAvailabilityStmt.setString(1, cloudLibraryId);
			ResultSet getExistingAvailabilityRS = getExistingCloudLibraryAvailabilityStmt.executeQuery();
			if (getExistingAvailabilityRS.next()) {
				long existingChecksum = getExistingAvailabilityRS.getLong("rawChecksum");
				logger.debug("Availability already exists");
				if (existingChecksum != availabilityChecksum) {
					logger.debug("Updating availability details");
					availabilityChanged = true;
				}
			} else {
				logger.debug("Adding availability for " + cloudLibraryId);
				availabilityChanged = true;
			}
		} catch (SQLException e) {
			logEntry.incErrors("Error loading availability", e);
		}

		if (availabilityChanged || doFullReload) {
			try {
				logEntry.incAvailabilityChanges();
				updateCloudLibraryAvailabilityStmt.setString(1, cloudLibraryId);
				updateCloudLibraryAvailabilityStmt.setLong(2, exporter.getSettingsId());
				updateCloudLibraryAvailabilityStmt.setLong(3, availability.getTotalCopies());
				updateCloudLibraryAvailabilityStmt.setLong(4, availability.getSharedCopies());
				updateCloudLibraryAvailabilityStmt.setLong(5, availability.getTotalLoanCopies());
				updateCloudLibraryAvailabilityStmt.setLong(6, availability.getTotalHoldCopies());
				updateCloudLibraryAvailabilityStmt.setLong(7, availability.getSharedLoanCopies());
				updateCloudLibraryAvailabilityStmt.setLong(8, availabilityChecksum);
				updateCloudLibraryAvailabilityStmt.setString(9, rawAvailabilityResponse);
				updateCloudLibraryAvailabilityStmt.setLong(10, startTimeForLogging);
				updateCloudLibraryAvailabilityStmt.executeUpdate();
			} catch (SQLException e) {
				logEntry.incErrors("Error saving availability", e);
			}
		}

		if (availabilityChanged || doFullReload) {
			logEntry.incUpdated();
			String groupedWorkId = recordGroupingProcessor.getPermanentIdForRecord("cloud_library", cloudLibraryId);
			indexer.processGroupedWork(groupedWorkId);
		}
	}
}