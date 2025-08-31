package java;

import java.util.*;
import java.io.*;
import java.sql.*;

public class LoyaltyManager {
    private static final int POINTS_PER_EURO = 1;
    private static final int POINTS_FOR_DISCOUNT = 100;
    private static final double DISCOUNT_AMOUNT = 5.0;
    

    private static final String DB_URL = "jdbc:mysql://localhost:3306/drivin_cook";
    private static final String DB_USER = "root";
    private static final String DB_PASS = "";
    public static boolean addPoints(int clientId, int points, String reason) {
        try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASS)) {

            String updateSql = "UPDATE clients SET loyalty_points = loyalty_points + ? WHERE id = ?";
            PreparedStatement updateStmt = conn.prepareStatement(updateSql);
            updateStmt.setInt(1, points);
            updateStmt.setInt(2, clientId);
            
            int rowsUpdated = updateStmt.executeUpdate();
            
            if (rowsUpdated > 0) {
                String historySql = "INSERT INTO loyalty_history (client_id, points_change, reason) VALUES (?, ?, ?)";
                PreparedStatement historyStmt = conn.prepareStatement(historySql);
                historyStmt.setInt(1, clientId);
                historyStmt.setInt(2, points);
                historyStmt.setString(3, reason);
                
                historyStmt.executeUpdate();
                return true;
            }
            
        } catch (SQLException e) {
            System.err.println("Erreur SQL: " + e.getMessage());
        }
        
        return false;
    }
    
    public static boolean usePoints(int clientId, int points, String reason) {
        try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASS)) {
            int currentPoints = getCurrentPoints(clientId);
            
            if (currentPoints >= points) {
                String updateSql = "UPDATE clients SET loyalty_points = loyalty_points - ? WHERE id = ?";
                PreparedStatement updateStmt = conn.prepareStatement(updateSql);
                updateStmt.setInt(1, points);
                updateStmt.setInt(2, clientId);
                
                updateStmt.executeUpdate();
                
                String historySql = "INSERT INTO loyalty_history (client_id, points_change, reason) VALUES (?, ?, ?)";
                PreparedStatement historyStmt = conn.prepareStatement(historySql);
                historyStmt.setInt(1, clientId);
                historyStmt.setInt(2, -points);
                historyStmt.setString(3, reason);
                
                historyStmt.executeUpdate();
                return true;
            }
            
        } catch (SQLException e) {
            System.err.println("Erreur SQL: " + e.getMessage());
        }
        
        return false;
    }
    

    public static int getCurrentPoints(int clientId) {
        try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASS)) {
            String sql = "SELECT loyalty_points FROM clients WHERE id = ?";
            PreparedStatement stmt = conn.prepareStatement(sql);
            stmt.setInt(1, clientId);
            
            ResultSet rs = stmt.executeQuery();
            if (rs.next()) {
                return rs.getInt("loyalty_points");
            }
            
        } catch (SQLException e) {
            System.err.println("Erreur SQL: " + e.getMessage());
        }
        
        return 0;
    }

    public static int calculatePointsEarned(double amount) {
        return (int) Math.floor(amount * POINTS_PER_EURO);
    }

    public static double calculateDiscount(int points) {
        int discountGroups = points / POINTS_FOR_DISCOUNT;
        return discountGroups * DISCOUNT_AMOUNT;
    }
    
    public static boolean canUsePoints(int clientId, int pointsNeeded) {
        return getCurrentPoints(clientId) >= pointsNeeded;
    }

    public static Map<String, Object> processOrderReward(int clientId, double orderAmount, int orderId) {
        Map<String, Object> result = new HashMap<>();
        
        try {

            int pointsEarned = calculatePointsEarned(orderAmount);
            
            boolean success = addPointsForOrder(clientId, pointsEarned, orderId);
            
            if (success) {
                result.put("success", true);
                result.put("points_earned", pointsEarned);
                result.put("total_points", getCurrentPoints(clientId));
                result.put("message", "Points ajoutés avec succès");
            } else {
                result.put("success", false);
                result.put("message", "Erreur lors de l'ajout des points");
            }
            
        } catch (Exception e) {
            result.put("success", false);
            result.put("message", "Erreur: " + e.getMessage());
        }
        
        return result;
    }
    
    private static boolean addPointsForOrder(int clientId, int points, int orderId) {
        try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASS)) {
            conn.setAutoCommit(false);
            
           
            String updateSql = "UPDATE clients SET loyalty_points = loyalty_points + ? WHERE id = ?";
            PreparedStatement updateStmt = conn.prepareStatement(updateSql);
            updateStmt.setInt(1, points);
            updateStmt.setInt(2, clientId);
            updateStmt.executeUpdate();
            
            
            String historySql = "INSERT INTO loyalty_history (client_id, points_change, reason, order_id) VALUES (?, ?, ?, ?)";
            PreparedStatement historyStmt = conn.prepareStatement(historySql);
            historyStmt.setInt(1, clientId);
            historyStmt.setInt(2, points);
            historyStmt.setString(3, "Points gagnés pour commande #" + orderId);
            historyStmt.setInt(4, orderId);
            historyStmt.executeUpdate();
            
            conn.commit();
            return true;
            
        } catch (SQLException e) {
            System.err.println("Erreur SQL: " + e.getMessage());
        }
        
        return false;
    }

    public static void main(String[] args) {
        if (args.length < 2) {
            System.out.println("Usage: java LoyaltyManager <action> <params...>");
            return;
        }
        
        String action = args[0];
        
        try {
            switch (action) {
                case "addPoints":
                    if (args.length >= 4) {
                        int clientId = Integer.parseInt(args[1]);
                        int points = Integer.parseInt(args[2]);
                        String reason = args[3];
                        boolean success = addPoints(clientId, points, reason);
                        System.out.println(success ? "SUCCESS" : "ERROR");
                    }
                    break;
                    
                case "usePoints":
                    if (args.length >= 4) {
                        int clientId = Integer.parseInt(args[1]);
                        int points = Integer.parseInt(args[2]);
                        String reason = args[3];
                        boolean success = usePoints(clientId, points, reason);
                        System.out.println(success ? "SUCCESS" : "ERROR");
                    }
                    break;
                    
                case "getCurrentPoints":
                    if (args.length >= 2) {
                        int clientId = Integer.parseInt(args[1]);
                        int points = getCurrentPoints(clientId);
                        System.out.println(points);
                    }
                    break;
                    
                case "calculatePoints":
                    if (args.length >= 2) {
                        double amount = Double.parseDouble(args[1]);
                        int points = calculatePointsEarned(amount);
                        System.out.println(points);
                    }
                    break;
                    
                case "processOrder":
                    if (args.length >= 4) {
                        int clientId = Integer.parseInt(args[1]);
                        double amount = Double.parseDouble(args[2]);
                        int orderId = Integer.parseInt(args[3]);
                        
                        Map<String, Object> result = processOrderReward(clientId, amount, orderId);
                        System.out.println("SUCCESS:" + result.get("points_earned") + ":" + result.get("total_points"));
                    }
                    break;
                    
                default:
                    System.out.println("Action non reconnue: " + action);
            }
            
        } catch (Exception e) {
            System.out.println("ERROR:" + e.getMessage());
        }
    }
}
